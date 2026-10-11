<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\ComunicacionOficial;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use App\Services\Interop\DocDigital\DocDigitalClient;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Support\UploadValidator;
use Illuminate\Support\Facades\DB;

/**
 * Comunicaciones oficiales del Estado vía DocDigital. Mismo principio que las notificaciones: el sistema NUNCA afirma
 * haber enviado algo sin evidencia (ID de DocDigital). Modo `outbox` (defecto): queda "pendiente" hasta registrar
 * el ID/folio obtenido manualmente en DocDigital. Solo documentos vigentes; en modos API se exige PDF con FEA real.
 */
class ComunicacionService
{
    public function __construct(
        private readonly AuditoriaService $audit,
        private readonly FileVault $vault,
        private readonly DocDigitalClient $client,
        private readonly CodificadorService $codificador,
    ) {}

    public function modoApi(): bool
    {
        return in_array(config('sgd.docdigital.mode'), ['mock', 'demo', 'prod'], true);
    }

    public function crearSaliente(Expediente $e, Documento $d, string $institucionCodigo, string $asunto, Usuario $u): ComunicacionOficial
    {
        $asunto = trim($asunto);
        if (mb_strlen($asunto) < 3 || mb_strlen($asunto) > 190 || preg_match('/[\r\n]/', $asunto)) {
            throw new FlujoException('El asunto debe tener entre 3 y 190 caracteres, en una línea.');
        }
        if ($d->expediente_id !== $e->id || $d->estado !== 'vigente') {
            throw new FlujoException('Solo se comunican documentos vigentes del expediente.');
        }
        $inst = $this->codificador->institucionActiva($institucionCodigo);
        if (! $inst) {
            throw new FlujoException('La institución destinataria no existe en el Codificador.');
        }
        if ($this->modoApi() && config('sgd.docdigital.mode') !== 'mock' && ! $d->pdf_firmado_ruta) {
            throw new FlujoException('DocDigital exige un PDF con firma electrónica avanzada; este documento no tiene firma real (¿firma simulada?).');
        }
        $dup = ComunicacionOficial::query()->where('direccion', 'saliente')->where('documento_id', $d->id)->where('institucion_codigo', $inst->codigo)->whereIn('estado', ['pendiente', 'enviada'])->exists();
        if ($dup) {
            throw new FlujoException('Ya existe una comunicación de este documento a esa institución.');
        }
        $c = ComunicacionOficial::query()->create([
            'direccion' => 'saliente', 'expediente_id' => $e->id, 'documento_id' => $d->id, 'institucion_codigo' => $inst->codigo, 'institucion_nombre' => $inst->nombre,
            'asunto' => $asunto, 'estado' => 'pendiente', 'creado_por' => $u->id, 'unidad_id' => $e->unidad_id,
        ]);
        $this->audit->registrar('docdigital.crear', 'comunicacion', $c->id, ['destino' => $inst->codigo, 'documento' => $d->id]);
        if ($this->modoApi()) {
            EnviarComunicacionDocDigital::dispatch($c->id);
        }

        return $c;
    }

    /** Envío idempotente (lo invoca el job). Falla cerrada: ante error queda "fallida" y se reintenta. */
    public function enviar(ComunicacionOficial $c): ComunicacionOficial
    {
        if ($c->estado === 'enviada' || $c->direccion !== 'saliente') {
            return $c;
        }
        $d = Documento::query()->with('tipo')->findOrFail($c->documento_id);
        try {
            $ruta = $d->pdf_firmado_ruta ?: $d->pdf_ruta;
            $sha = $d->pdf_firmado_ruta ? $d->pdf_firmado_sha256 : $d->pdf_sha256;
            $pdf = $this->vault->get((string) $ruta, (string) $sha); // verifica integridad antes de enviar
            $r = $this->client->enviar($pdf, 'documento-'.$d->numero.'-'.$d->anio.'.pdf', [
                'asunto' => $c->asunto, 'destino_codigo' => (string) $c->institucion_codigo, 'origen_codigo' => config('sgd.institucion_codigo'),
                'numero' => $d->numeroFormateado(), 'tipo' => $d->tipo->nombre,
            ]);
        } catch (\Throwable $e) {
            $c->forceFill(['estado' => 'fallida', 'intentos' => $c->intentos + 1, 'ultimo_error' => $e instanceof DocDigitalException ? mb_substr($e->getMessage(), 0, 120) : 'Fallo de envío'])->save();
            $this->audit->registrar('docdigital.fallo', 'comunicacion', $c->id, ['intento' => $c->intentos], false, ['run' => null, 'rol' => 'sistema']);
            throw $e;
        }
        $c->forceFill(['estado' => 'enviada', 'evidencia_tipo' => $r['simulado'] ? 'simulado' : 'docdigital_id', 'evidencia' => $r['id'], 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        $this->audit->registrar('docdigital.enviada', 'comunicacion', $c->id, ['simulado' => $r['simulado']], false, ['run' => null, 'rol' => 'sistema']);

        return $c;
    }

    public function registrarEvidencia(ComunicacionOficial $c, string $folio): ComunicacionOficial
    {
        $folio = trim($folio);
        if ($c->direccion !== 'saliente' || $c->estado === 'enviada' || $folio === '' || mb_strlen($folio) > 120 || preg_match('/[\r\n]/', $folio)) {
            throw new FlujoException('No se puede registrar esa evidencia.');
        }
        if ($this->modoApi()) {
            throw new FlujoException('En modo API la evidencia la registra el sistema.');
        }
        $c->forceFill(['estado' => 'enviada', 'evidencia_tipo' => 'docdigital_manual', 'evidencia' => $folio, 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        $this->audit->registrar('docdigital.evidencia', 'comunicacion', $c->id);

        return $c;
    }

    /** @return array{nuevas:int,omitidas:int} */
    public function sincronizarRecibidos(): array
    {
        $unidad = Unidad::query()->where('codigo', config('sgd.docdigital.unidad_recepcion'))->first();
        $res = ['nuevas' => 0, 'omitidas' => 0];
        foreach ($this->client->recibidos() as $r) {
            if (ComunicacionOficial::query()->where('direccion', 'entrante')->where('evidencia_tipo', 'docdigital_id')->where('evidencia', $r['id'])->exists()) {
                continue;
            }
            try {
                UploadValidator::validarBytes($r['pdf']);
                if (! str_starts_with($r['pdf'], '%PDF-')) {
                    throw new \InvalidArgumentException('no es PDF');
                }
            } catch (\InvalidArgumentException) {
                $res['omitidas']++;
                $this->audit->registrar('docdigital.entrante_rechazado', null, null, ['id' => $r['id']], false, ['run' => null, 'rol' => 'sistema']);

                continue;
            }
            DB::transaction(function () use ($r, $unidad, &$res) {
                $f = $this->vault->put($r['pdf']);
                $a = Archivo::query()->create([
                    'ambito' => 'docdigital', 'nombre_original' => 'docdigital-'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $r['id']).'.pdf', 'mime' => 'application/pdf', 'tamano' => $f['tamano'],
                    'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => mb_substr($r['asunto'], 0, 190), 'nivel_acceso' => 'reservado', 'sensible' => true, 'subido_por' => 'docdigital',
                ]);
                ComunicacionOficial::query()->create([
                    'direccion' => 'entrante', 'institucion_codigo' => $r['origen_codigo'] ? mb_substr($r['origen_codigo'], 0, 40) : null,
                    'institucion_nombre' => $r['origen_nombre'] ? mb_substr($r['origen_nombre'], 0, 190) : null, 'asunto' => mb_substr($r['asunto'], 0, 190), 'estado' => 'recibida',
                    'evidencia_tipo' => 'docdigital_id', 'evidencia' => $r['id'], 'evidencia_en' => now(), 'archivo_id' => $a->id, 'unidad_id' => $unidad?->id,
                ]);
                $res['nuevas']++;
            });
        }
        $this->audit->registrar('docdigital.sincronizar', null, null, $res, false, ['run' => null, 'rol' => 'sistema']);

        return $res;
    }
}
