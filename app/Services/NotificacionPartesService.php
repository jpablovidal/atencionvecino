<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarNotificacionCorreo;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Services\Interop\ComunicacionService;
use Illuminate\Support\Facades\DB;

/**
 * Notificación de los documentos de un expediente cerrado a sus partes, cada una por su canal:
 * vecinos (Casilla Única, correo o entrega física), funcionarios (aviso interno con acción pendiente), externos (correo o entrega física)
 * y organismos públicos (comunicación oficial por DocDigital, a la entidad y a las personas indicadas).
 */
class NotificacionPartesService
{
    public function __construct(
        private readonly AuditoriaService $audit,
        private readonly NotificacionService $notificaciones,
        private readonly ComunicacionService $comunicaciones,
    ) {}

    /**
     * @param  list<int>  $parteIds
     * @param  list<int>  $documentoIds
     * @return list<Notificacion>
     *
     * @throws FlujoException
     */
    public function notificar(Expediente $e, array $parteIds, array $documentoIds, Usuario $u): array
    {
        if ($e->estado !== 'cerrado') {
            throw new FlujoException('Solo se notifica con el expediente cerrado.');
        }
        $partes = ExpedienteParte::query()->where('expediente_id', $e->id)->whereIn('id', $parteIds)->get();
        if ($partes->isEmpty() || $partes->count() !== count(array_unique($parteIds))) {
            throw new FlujoException('Elija al menos una parte del expediente.');
        }
        $docs = Documento::query()->where('expediente_id', $e->id)->whereIn('id', $documentoIds)->where('estado', 'vigente')->get();
        if ($docs->isEmpty() || $docs->count() !== count(array_unique($documentoIds))) {
            throw new FlujoException('Seleccione documentos vigentes del expediente.');
        }
        $salida = [];
        $errores = [];
        foreach ($partes as $p) {
            foreach ($docs as $d) {
                if (Notificacion::query()->where('parte_id', $p->id)->where('documento_id', $d->id)->where('estado', '!=', 'fallida')->exists()) {
                    continue; // ya notificado (o en curso)
                }
                try {
                    $salida[] = $this->una($e, $p, $d, $u);
                } catch (FlujoException $x) {
                    $errores[] = $p->nombre.': '.$x->getMessage();
                }
            }
        }
        $this->audit->registrar('notificacion.partes', 'expediente', $e->id, ['partes' => $partes->count(), 'documentos' => $docs->count(), 'creadas' => count($salida), 'errores' => count($errores)]);
        if (! $salida && $errores) {
            throw new FlujoException(implode(' · ', array_slice($errores, 0, 3)));
        }
        if (! $salida) {
            throw new FlujoException('Esas partes ya fueron notificadas de esos documentos.');
        }
        if ($errores) {
            session()->flash('aviso', 'Algunas notificaciones no se crearon: '.implode(' · ', array_slice($errores, 0, 3)));
        }

        return $salida;
    }

    private function una(Expediente $e, ExpedienteParte $p, Documento $d, Usuario $u): Notificacion
    {
        $base = ['expediente_id' => $e->id, 'documento_id' => $d->id, 'parte_id' => $p->id, 'estado' => 'pendiente', 'creado_por' => $u->id, 'destinatario_nombre' => $p->nombre, 'destinatario_run' => $p->run];

        if ($p->tipo === 'organismo') {
            $c = $this->comunicaciones->crearSalienteEntidad($e, $d, (int) $p->dd_entidad_id, mb_substr('Notificación: '.$d->titulo, 0, 190), $u, [], array_map(fn ($x) => (int) $x['id'], $p->personasDd()));
            $c->refresh();

            return Notificacion::query()->create(array_merge($base, ['canal' => 'docdigital', 'comunicacion_id' => $c->id, 'estado' => $c->estado === 'enviada' ? 'enviada' : 'pendiente',
                'evidencia_tipo' => $c->evidencia_tipo, 'evidencia' => $c->evidencia, 'evidencia_en' => $c->evidencia_en]));
        }
        if ($p->tipo === 'funcionario') {
            return DB::transaction(function () use ($base, $e, $p, $d, $u) {
                $der = Derivacion::query()->create(['expediente_id' => $e->id, 'de_usuario_id' => $u->id, 'a_usuario_id' => $p->usuario_id, 'accion' => 'conocimiento', 'estado' => 'pendiente',
                    'motivo' => 'Se le notifica el documento '.$d->numeroFormateado().' — '.$d->titulo.' (expediente '.$e->codigo.').']);

                return Notificacion::query()->create(array_merge($base, ['canal' => 'interno', 'estado' => 'enviada', 'evidencia_tipo' => 'derivacion_interna', 'evidencia' => (string) $der->id, 'evidencia_en' => now()]));
            });
        }
        $canal = (string) $p->canal;
        if ($canal === 'correo' && ! $p->correo) {
            throw new FlujoException('falta el correo electrónico');
        }
        if ($canal === 'casilla_unica' && ! $p->run) {
            throw new FlujoException('falta el RUN');
        }
        $n = Notificacion::query()->create(array_merge($base, ['canal' => $canal, 'destinatario_correo' => $canal === 'correo' ? $p->correo : null]));
        if ($canal === 'correo') {
            EnviarNotificacionCorreo::dispatch($n->id);
        } elseif ($canal === 'casilla_unica') {
            $this->notificaciones->intentarCasilla($n);
        }

        return $n;
    }
}
