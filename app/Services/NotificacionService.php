<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarNotificacionCorreo;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Services\Casilla\CasillaAdapter;
use Illuminate\Support\Facades\DB;

class NotificacionService
{
    public const CANALES = ['casilla_unica', 'correo', 'presencial'];

    public function __construct(private readonly AuditoriaService $audit, private readonly CasillaAdapter $casilla) {}

    /** @param array<int,int> $documentoIds @return array<int,Notificacion> */
    public function crear(Expediente $e, array $documentoIds, string $canal, Usuario $u): array
    {
        if ($e->estado !== 'cerrado') {
            throw new FlujoException('Solo se notifica con el expediente cerrado.');
        }
        if (! in_array($canal, self::CANALES, true)) {
            throw new FlujoException('Canal inválido.');
        }
        $docs = $e->documentos()->whereIn('id', $documentoIds)->where('estado', 'vigente')->get();
        if ($docs->isEmpty() || $docs->count() !== count(array_unique($documentoIds))) {
            throw new FlujoException('Seleccione documentos vigentes del expediente.');
        }
        $s = $e->solicitud;
        $out = DB::transaction(function () use ($docs, $e, $s, $canal, $u) {
            $r = [];
            foreach ($docs as $d) {
                $r[] = Notificacion::query()->create([
                    'expediente_id' => $e->id, 'documento_id' => $d->id, 'solicitud_id' => $s->id, 'canal' => $canal, 'estado' => 'pendiente',
                    'destinatario_run' => $s->run, 'destinatario_correo' => $canal === 'correo' ? $s->correo : null, 'creado_por' => $u->id,
                ]);
            }
            $this->audit->registrar('notificacion.crear', 'expediente', $e->id, ['canal' => $canal, 'documentos' => $docs->count()]);

            return $r;
        });
        foreach ($out as $n) {
            if ($canal === 'correo') {
                EnviarNotificacionCorreo::dispatch($n->id);
            } elseif ($canal === 'casilla_unica') {
                $this->intentarCasilla($n);
            }
        }

        return $out;
    }

    private function intentarCasilla(Notificacion $n): void
    {
        try {
            $ev = $this->casilla->enviar($n);
        } catch (\Throwable $e) {
            $n->forceFill(['estado' => 'fallida', 'intentos' => $n->intentos + 1, 'ultimo_error' => 'Casilla Única no confirmó el envío'])->save();

            return;
        }
        if ($ev) {
            $this->marcarEnviada($n, $ev['evidencia_tipo'], $ev['evidencia']);
        } // outbox: permanece "pendiente" hasta registrar evidencia
    }

    /** Un funcionario registra la evidencia real (acuse de Casilla Única o acta de entrega presencial). */
    public function registrarEvidencia(Notificacion $n, string $tipo, string $valor): Notificacion
    {
        if ($n->canal === 'correo') {
            throw new FlujoException('La evidencia de correo la registra solo el sistema (ID de mensaje Gmail).');
        }
        if (! in_array($tipo, ['id_casilla_unica', 'acta_presencial'], true) || trim($valor) === '') {
            throw new FlujoException('Evidencia inválida.');
        }

        return $this->marcarEnviada($n, $tipo, mb_substr(trim($valor), 0, 250));
    }

    public function marcarEnviada(Notificacion $n, string $tipo, string $evidencia): Notificacion
    {
        $n->forceFill(['estado' => 'enviada', 'evidencia_tipo' => $tipo, 'evidencia' => $evidencia, 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        $this->audit->registrar('notificacion.evidencia', 'notificacion', $n->id, ['canal' => $n->canal, 'tipo' => $tipo]);

        return $n;
    }
}
