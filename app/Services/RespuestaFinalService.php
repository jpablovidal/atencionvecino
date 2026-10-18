<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarNotificacionCorreo;
use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\RespuestaFinal;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Respuesta final de notificación. Quien gestionó el expediente INSTRUYE: confirma que enviará la respuesta final e indica los documentos y
 * archivos del expediente que se entregan. Quien responde (p. ej. Oficina de Partes) la DESPACHA: Casilla Única (notificador) con copia al
 * correo, o excepcionalmente entrega física con acta. Cada paso queda en la auditoría y en el historial que ve el ciudadano.
 */
class RespuestaFinalService
{
    public const CANALES = ['casilla_unica' => 'Casilla Única (con copia al correo)', 'presencial' => 'Entrega física (excepcional)'];

    /** Archivos entregables: no se entregan consultas a otros órganos (PISEE) ni el comprobante de ingreso. */
    public const AMBITOS = ['expediente', 'docdigital', 'anexo', 'respuesta'];

    public function __construct(
        private readonly AuditoriaService $audit, private readonly NotificacionService $notif, private readonly MovimientoService $mov,
        private readonly AccesoRestringidoService $restringidos,
    ) {}

    private function autorizado(callable $exigir): void
    {
        try {
            $exigir();
        } catch (HttpException) {
            throw new FlujoException('Hay contenido restringido para el que no está autorizado(a): no puede entregarlo. El intento queda registrado.');
        }
    }

    private function texto(?string $t, int $max): string
    {
        return mb_substr(TramiteService::limpiarTexto($t), 0, $max);
    }

    /**
     * @param  array<int,int>  $documentoIds
     * @param  array<int,int>  $archivoIds
     */
    public function instruir(Usuario $u, Expediente $e, array $documentoIds, array $archivoIds, string $canal, ?string $motivoPresencial, ?string $instruccion, bool $confirma): RespuestaFinal
    {
        if (! $u->tienePermiso('respuestas.instruir') || ! $u->can('operar', $e)) {
            throw new FlujoException('No tiene permiso para indicar la respuesta de este expediente.');
        }
        if (! $confirma) {
            throw new FlujoException('Confirme que enviará la respuesta final de notificación.');
        }
        $s = $e->solicitud ?? throw new FlujoException('Este expediente no tiene un solicitante ciudadano a quien responder; responda por DocDigital.');
        if (! isset(self::CANALES[$canal])) {
            throw new FlujoException('Canal inválido.');
        }
        if (RespuestaFinal::query()->where('expediente_id', $e->id)->where('estado', 'instruida')->exists()) {
            throw new FlujoException('Ya hay una respuesta final instruida para este expediente: anúlela para indicar otra.');
        }
        $motivo = null;
        if ($canal === 'presencial') {
            $motivo = $this->texto($motivoPresencial, 300);
            if (mb_strlen($motivo) < 10) {
                throw new FlujoException('La entrega física es excepcional: indique el motivo (mínimo 10 caracteres).');
            }
        }
        $documentoIds = array_values(array_unique(array_map('intval', $documentoIds)));
        $archivoIds = array_values(array_unique(array_map('intval', $archivoIds)));
        if (! $documentoIds && ! $archivoIds) {
            throw new FlujoException('Seleccione al menos un documento o archivo del expediente.');
        }
        if (count($documentoIds) + count($archivoIds) > 50) {
            throw new FlujoException('Máximo 50 elementos por respuesta.');
        }
        $docs = Documento::query()->where('expediente_id', $e->id)->whereIn('id', $documentoIds)->where('estado', 'vigente')->get();
        $arch = Archivo::query()->where('expediente_id', $e->id)->whereIn('id', $archivoIds)->whereIn('ambito', self::AMBITOS)->get();
        if ($docs->count() !== count($documentoIds) || $arch->count() !== count($archivoIds)) {
            throw new FlujoException('Seleccione solo documentos vigentes y archivos del expediente que puedan entregarse al solicitante.');
        }
        foreach ($docs as $d) {
            $this->autorizado(fn () => $this->restringidos->exigirDocumento($u, $d, 'respuesta_final'));
        }
        foreach ($arch as $a) {
            $this->autorizado(fn () => $this->restringidos->exigirArchivo($u, $a, 'respuesta_final'));
        }

        return DB::transaction(function () use ($u, $e, $s, $canal, $motivo, $instruccion, $docs, $arch) {
            $r = RespuestaFinal::query()->create(['expediente_id' => $e->id, 'solicitud_id' => $s->id, 'estado' => 'instruida', 'canal' => $canal, 'motivo_presencial' => $motivo,
                'instruccion' => $this->texto($instruccion, 600) ?: null, 'instruida_por' => $u->id]);
            foreach ($docs as $d) {
                $r->items()->create(['documento_id' => $d->id]);
            }
            foreach ($arch as $a) {
                $r->items()->create(['archivo_id' => $a->id]);
            }
            $this->audit->registrar('respuesta.instruir', 'expediente', $e->id, ['respuesta' => $r->id, 'canal' => $canal, 'items' => $docs->count() + $arch->count()]);
            $this->mov->registrar($s, 'respuesta_instruida', 'Se está preparando la respuesta final a su solicitud.', $e->unidad_id);

            return $r;
        });
    }

    public function anular(Usuario $u, RespuestaFinal $r, string $motivo): void
    {
        $e = $r->expediente;
        if (! ($u->tienePermiso('respuestas.despachar') || ($u->tienePermiso('respuestas.instruir') && $u->can('operar', $e)))) {
            throw new FlujoException('No tiene permiso para anular esta respuesta.');
        }
        $motivo = $this->texto($motivo, 300);
        if (mb_strlen($motivo) < 5) {
            throw new FlujoException('Indique el motivo de la anulación.');
        }
        DB::transaction(function () use ($r, $motivo) {
            $r = RespuestaFinal::query()->lockForUpdate()->findOrFail($r->id);
            if ($r->estado !== 'instruida') {
                throw new FlujoException('Solo se anulan respuestas instruidas que aún no se despacharon.');
            }
            $r->forceFill(['estado' => 'anulada', 'anulada_motivo' => $motivo])->save();
            $this->audit->registrar('respuesta.anular', 'expediente', $r->expediente_id, ['respuesta' => $r->id]);
        });
    }

    /** @return array<int,Notificacion> */
    public function despachar(Usuario $u, RespuestaFinal $r, ?string $acta): array
    {
        if (! $u->tienePermiso('respuestas.despachar')) {
            throw new FlujoException('No tiene permiso para responder al solicitante.');
        }
        $acta = $this->texto($acta, 250);
        $creadas = DB::transaction(function () use ($u, $r, $acta) {
            $r = RespuestaFinal::query()->lockForUpdate()->with('expediente.solicitud', 'items')->findOrFail($r->id);
            if ($r->estado !== 'instruida') {
                throw new FlujoException('La respuesta ya fue despachada o anulada.');
            }
            $e = $r->expediente;
            if ($e->estado !== 'cerrado') {
                throw new FlujoException('El expediente aún está abierto: quien lo gestiona debe cerrarlo antes de despachar la respuesta final.');
            }
            $s = $e->solicitud ?? throw new FlujoException('El expediente no tiene solicitante.');
            if ($r->canal === 'presencial' && mb_strlen($acta) < 10) {
                throw new FlujoException('Registre el acta de entrega física (quién recibe, fecha y hora; mínimo 10 caracteres).');
            }
            foreach ($r->items as $it) {
                if ($it->documento_id && ! Documento::query()->where('id', $it->documento_id)->where('estado', 'vigente')->exists()) {
                    throw new FlujoException('Un documento seleccionado dejó de estar vigente: pida a quien gestionó la solicitud que indique la respuesta nuevamente.');
                }
            }
            $out = [];
            foreach ($r->items as $it) {
                $out[] = Notificacion::query()->create(['expediente_id' => $e->id, 'documento_id' => $it->documento_id, 'archivo_id' => $it->archivo_id, 'respuesta_id' => $r->id, 'solicitud_id' => $s->id,
                    'canal' => $r->canal, 'estado' => 'pendiente', 'destinatario_run' => $s->run, 'creado_por' => $u->id]);
            }
            if ($r->canal === 'casilla_unica') {
                $out[] = Notificacion::query()->create(['expediente_id' => $e->id, 'respuesta_id' => $r->id, 'solicitud_id' => $s->id, 'canal' => 'correo', 'estado' => 'pendiente',
                    'destinatario_run' => $s->run, 'destinatario_correo' => $s->correo, 'creado_por' => $u->id]); // copia del aviso
            } else {
                foreach ($out as $n) {
                    $this->notif->marcarEnviada($n, 'acta_presencial', $acta);
                }
            }
            $r->forceFill(['estado' => 'despachada', 'despachada_por' => $u->id, 'despachada_en' => now()])->save();
            $this->audit->registrar('respuesta.despachar', 'expediente', $e->id, ['respuesta' => $r->id, 'canal' => $r->canal]);
            $this->mov->registrar($s, 'respuesta_despachada', $r->canal === 'presencial'
                ? 'Se le entregó físicamente la respuesta final a su solicitud.'
                : 'Se despachó la respuesta final a su solicitud: la recibirá en su Casilla Única, con copia a su correo electrónico.', $e->unidad_id);

            return $out;
        });
        foreach ($creadas as $n) {
            if ($n->canal === 'correo') {
                EnviarNotificacionCorreo::dispatch($n->id);
            } elseif ($n->canal === 'casilla_unica') {
                $this->notif->intentarCasilla($n);
            }
        }

        return $creadas;
    }

    /** El funcionario que responde registra el acuse de Casilla Única (modo outbox). */
    public function registrarEvidencia(Usuario $u, RespuestaFinal $r, Notificacion $n, string $valor): void
    {
        if (! $u->tienePermiso('respuestas.despachar') || (int) $n->respuesta_id !== $r->id) {
            throw new FlujoException('No tiene permiso para registrar esta evidencia.');
        }
        if ($n->canal !== 'casilla_unica') {
            throw new FlujoException('Solo se registra a mano la evidencia de Casilla Única.');
        }
        $this->notif->registrarEvidencia($n, 'id_casilla_unica', $valor);
    }

    /** Reintenta lo pendiente o fallido: Casilla Única (modo API) y copia por correo. @return int cantidad reintentada */
    public function reintentar(Usuario $u, RespuestaFinal $r): int
    {
        if (! $u->tienePermiso('respuestas.despachar')) {
            throw new FlujoException('No tiene permiso para reintentar envíos.');
        }
        $n = 0;
        foreach ($r->notificaciones()->whereIn('estado', ['pendiente', 'fallida'])->get() as $x) {
            if ($x->canal === 'correo') {
                EnviarNotificacionCorreo::dispatch($x->id);
                $n++;
            } elseif ($x->canal === 'casilla_unica') {
                try {
                    $this->notif->reintentarCasilla($x);
                    $n++;
                } catch (FlujoException) {
                    // modo outbox: se registra la evidencia a mano
                }
            }
        }
        $this->audit->registrar('respuesta.reintento', 'expediente', $r->expediente_id, ['respuesta' => $r->id, 'reintentos' => $n]);

        return $n;
    }
}
