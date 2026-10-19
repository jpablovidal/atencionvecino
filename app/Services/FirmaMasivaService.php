<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\DocumentoPaso;
use App\Models\Usuario;
use App\Support\Plazos;
use Illuminate\Support\Collection;

/**
 * Firma y visación por lote: la persona elige varios documentos (o todos) de los que le toca firmar o visar y los resuelve de una vez.
 * Cada documento sigue el mismo camino que la firma individual (turno, restricción, integridad del PDF, tipo de firma permitido,
 * número correlativo, auditoría). Nunca se salta el turno ni se firma algo restringido sin autorización. Con firma avanzada el lote
 * se detiene ante el primer fallo del servicio de firma, para no repetir un código OTP posiblemente inválido.
 */
class FirmaMasivaService
{
    public const LOTE_DEFECTO = 25;

    public function __construct(private readonly DocumentoService $docs, private readonly AccesoRestringidoService $restringidos, private readonly AuditoriaService $audit) {}

    public static function loteMax(): int
    {
        return max(1, min(100, (int) config('sgd.firma_lote_max', self::LOTE_DEFECTO)));
    }

    /**
     * Pasos pendientes de la persona en los que ya le toca (documento en flujo y sin pasos anteriores pendientes).
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function pendientes(Usuario $u, ?string $tipoPaso = null): Collection
    {
        $hoy = now(Plazos::TZ)->startOfDay();
        $q = DocumentoPaso::query()->with('documento.tipo', 'documento.expediente.solicitud.tramite')->where('usuario_id', $u->id)->where('estado', 'pendiente')
            ->whereHas('documento', fn ($d) => $d->where('estado', 'en_flujo'))->orderBy('created_at')->orderBy('id');
        if ($tipoPaso) {
            $q->where('tipo', $tipoPaso);
        }

        return $q->get()->filter(fn (DocumentoPaso $p) => $this->esSuTurno($p))->map(function (DocumentoPaso $p) use ($u, $hoy) {
            $d = $p->documento;
            $oculto = ! $this->restringidos->puedeVerDocumento($u, $d);

            return ['paso' => $p, 'documento' => $d, 'oculto' => $oculto, 'restringido' => $this->restringidos->restringidoDocumento($d), 'dias' => (int) $p->created_at->setTimezone(Plazos::TZ)->startOfDay()->diffInDays($hoy, false),
                'simple' => in_array($p->firma_permitida, ['simple', 'ambas'], true), 'avanzada' => in_array($p->firma_permitida ?: 'avanzada', ['avanzada', 'ambas'], true)];
        })->values();
    }

    private function esSuTurno(DocumentoPaso $p): bool
    {
        return ! DocumentoPaso::query()->where('documento_id', $p->documento_id)->where('estado', 'pendiente')->where('orden', '<', $p->orden)->exists();
    }

    /**
     * @param  array<int,int>  $pasoIds
     * @return array<int,array{ok:bool,documento:string,mensaje:string}>
     */
    public function firmar(Usuario $u, array $pasoIds, bool $todos, string $tipo, ?string $otp, bool $reviso): array
    {
        if (! $u->operaExpedientes() || ! $u->puede_firmar) {
            throw new FlujoException('No está habilitado(a) para firmar.');
        }
        if (! in_array($tipo, ['avanzada', 'simple'], true)) {
            throw new FlujoException('Elija el tipo de firma.');
        }
        if (! $reviso) {
            throw new FlujoException('Confirme que revisó los documentos que va a firmar.');
        }
        if ($tipo === 'avanzada' && ! preg_match('/^\d{6}$/', (string) $otp)) {
            throw new FlujoException('Ingrese el código OTP de 6 dígitos.');
        }
        $items = $this->seleccion($u, 'firma', $pasoIds, $todos, fn ($i) => $tipo === 'simple' ? $i['simple'] : $i['avanzada']);
        $res = [];
        $detenido = false;
        foreach ($items as $i) {
            $d = $i['documento'];
            $nombre = $i['oculto'] ? 'Documento restringido' : $d->titulo;
            if ($detenido) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => 'No procesado: el lote se detuvo tras un fallo del servicio de firma.'];

                continue;
            }
            if ($i['oculto'] || ! $u->can('ver', $d)) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => 'Omitido: contenido restringido sin su autorización.'];

                continue;
            }
            if (! ($tipo === 'simple' ? $i['simple'] : $i['avanzada'])) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => 'Omitido: este paso no admite firma '.($tipo === 'simple' ? 'simple' : 'avanzada').'.'];

                continue;
            }
            try {
                $doc = $this->docs->firmar($d, $u, $tipo === 'avanzada' ? $otp : null, $tipo);
                $res[$i['paso']->id] = ['ok' => true, 'documento' => $nombre, 'mensaje' => $doc->estado === 'vigente' ? 'Firmado y vigente: '.$doc->numeroFormateado() : 'Firma registrada (faltan otras firmas).'];
            } catch (FlujoException $e) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => $e->getMessage()];
                if ($tipo === 'avanzada') {
                    $detenido = true;
                }
            }
        }
        $this->audit->registrar('firma.masiva', null, null, ['tipo' => $tipo, 'solicitados' => count($items), 'firmados' => collect($res)->where('ok', true)->count(), 'todos' => $todos], true);

        return $res;
    }

    /**
     * Visación por lote (solo aprobar: rechazar exige un comentario y se hace documento por documento).
     *
     * @param  array<int,int>  $pasoIds
     * @return array<int,array{ok:bool,documento:string,mensaje:string}>
     */
    public function visar(Usuario $u, array $pasoIds, bool $todos, ?string $comentario, bool $reviso): array
    {
        if (! $reviso) {
            throw new FlujoException('Confirme que revisó los documentos que va a visar.');
        }
        $comentario = $comentario !== null ? mb_substr(TramiteService::limpiarTexto($comentario), 0, 1000) : null;
        $res = [];
        foreach ($this->seleccion($u, 'visacion', $pasoIds, $todos, fn () => true) as $i) {
            $d = $i['documento'];
            $nombre = $i['oculto'] ? 'Documento restringido' : $d->titulo;
            if ($i['oculto'] || ! $u->can('ver', $d)) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => 'Omitido: contenido restringido sin su autorización.'];

                continue;
            }
            try {
                $this->docs->visar($d, $u, true, $comentario ?: null);
                $res[$i['paso']->id] = ['ok' => true, 'documento' => $nombre, 'mensaje' => 'Visado.'];
            } catch (FlujoException $e) {
                $res[$i['paso']->id] = ['ok' => false, 'documento' => $nombre, 'mensaje' => $e->getMessage()];
            }
        }
        $this->audit->registrar('visacion.masiva', null, null, ['solicitados' => count($res), 'visados' => collect($res)->where('ok', true)->count(), 'todos' => $todos], false);

        return $res;
    }

    /**
     * Elementos elegidos (o todos los del tipo de paso), solo de la persona y en su turno, hasta el máximo del lote.
     *
     * @param  array<int,int>  $pasoIds
     * @return Collection<int,array<string,mixed>>
     */
    private function seleccion(Usuario $u, string $tipoPaso, array $pasoIds, bool $todos, callable $filtroTodos): Collection
    {
        $pend = $this->pendientes($u, $tipoPaso);
        $ids = array_values(array_unique(array_map('intval', $pasoIds)));
        if ($todos) {
            $sel = $pend->filter(fn ($i) => $filtroTodos($i) && ! $i['oculto'])->values();
        } else {
            if (! $ids) {
                throw new FlujoException('Seleccione al menos un documento.');
            }
            $sel = $pend->filter(fn ($i) => in_array($i['paso']->id, $ids, true))->values();
            if ($sel->count() !== count($ids)) {
                throw new FlujoException('Algún documento seleccionado ya no está pendiente o aún no es su turno: actualice la lista.');
            }
        }
        if ($sel->isEmpty()) {
            throw new FlujoException('No hay documentos pendientes para procesar.');
        }
        if ($sel->count() > self::loteMax()) {
            if (! $todos) {
                throw new FlujoException('Máximo '.self::loteMax().' documentos por lote.');
            }
            $sel = $sel->take(self::loteMax()); // «todos»: se procesa el primer tramo y se repite
        }

        return $sel;
    }
}
