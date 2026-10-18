<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Models\Solicitud;
use App\Models\Unidad;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ExpedienteService
{
    public function __construct(private readonly AuditoriaService $audit, private readonly MovimientoService $mov) {}

    public function crearDesdeSolicitud(Solicitud $s, Usuario $u): Expediente
    {
        return DB::transaction(function () use ($s, $u) {
            $s = Solicitud::query()->with('tramite.unidades')->lockForUpdate()->findOrFail($s->id);
            if ($s->expediente_id) {
                throw new FlujoException('La solicitud ya fue asignada.');
            }
            $unidadIds = $s->tramite->unidades->pluck('id');
            if (! $u->unidad_id || ! $unidadIds->contains($u->unidad_id)) {
                throw new FlujoException('La solicitud no corresponde a su unidad.');
            }
            $exp = Expediente::query()->create([
                'codigo' => 'TMP', 'solicitud_id' => $s->id, 'unidad_id' => $u->unidad_id, 'responsable_id' => $u->id, 'estado' => 'abierto', 'metadatos' => [],
            ]);
            $exp->forceFill(['codigo' => sprintf('EXP-%d-%06d', now('America/Santiago')->year, $exp->id)])->save();
            $exp->forceFill(['metadatos' => $this->mgdee($exp->refresh(), $s)])->save();
            $s->forceFill(['expediente_id' => $exp->id, 'estado' => 'en_tramite'])->save();
            $s->archivos()->update(['expediente_id' => $exp->id]);
            $this->audit->registrar('expediente.crear', 'expediente', $exp->id, ['folio' => $s->folio]);
            $this->mov->registrar($s->refresh(), 'expediente', 'Se abrió el expediente N° '.$exp->codigo.' y su solicitud pasó a gestión.', $exp->unidad_id);

            return $exp;
        });
    }

    /**
     * Identificador único del expediente: `{código de la institución en el Codificador}-{EXP-año-correlativo}`.
     * Mismo código interno para solicitudes y comunicaciones recibidas (EXP-AAAA-NNNNNN, único por restricción de BD).
     * El esquema oficial de identificación de las normas técnicas es A VALIDAR.
     */
    public function identificador(Expediente $e): string
    {
        return ((string) config('sgd.institucion_codigo') ?: 'SGD').'-'.$e->codigo;
    }

    /** Crea el expediente de una comunicación oficial recibida por DocDigital (sin responsable hasta que alguien se la asigne). */
    public function crearDesdeComunicacion(ComunicacionOficial $c, Unidad $unidad): Expediente
    {
        return DB::transaction(function () use ($c, $unidad) {
            $e = Expediente::query()->create([
                'codigo' => 'TMP', 'solicitud_id' => null, 'unidad_id' => $unidad->id, 'responsable_id' => null, 'estado' => 'abierto',
                'origen' => 'docdigital', 'comunicacion_id' => $c->id, 'metadatos' => [],
            ]);
            $e->forceFill(['codigo' => sprintf('EXP-%d-%06d', now('America/Santiago')->year, $e->id)])->save();
            $now = now('America/Santiago')->toIso8601String();
            $e->forceFill(['metadatos' => [
                'identificacion' => ['codigo' => $e->codigo, 'identificador_unico' => $this->identificador($e), 'institucion_codigo' => config('sgd.institucion_codigo'),
                    'nombre' => 'Comunicación oficial: '.$c->asunto, 'organismo' => 'I. Municipalidad de Santo Domingo', 'unidad' => $unidad->nombre],
                'descripcion' => ['resumen' => mb_substr($c->asunto, 0, 500), 'folio_docdigital' => $c->evidencia],
                'temporalidad' => ['fecha_apertura' => $now, 'fecha_cierre' => null, 'fecha_recepcion' => $c->evidencia_en?->toIso8601String()],
                'caracterizacion' => ['tipo_expediente' => 'Comunicación oficial recibida', 'serie_documental' => null],
                'relaciones' => ['comunicacion_id' => $c->id, 'institucion_origen_codigo' => $c->institucion_codigo, 'institucion_origen' => $c->institucion_nombre, 'expedientes_relacionados' => []],
                'poderes' => ['responsable_run' => null, 'representacion' => 'Órgano de la Administración del Estado'],
                'seguridad' => ['nivel_acceso' => 'reservado', 'datos_personales' => false],
                'procedimiento' => ['codigo_cpat' => null, 'codigo_proceso' => null, 'tramite' => 'Comunicación oficial (DocDigital)'],
                'version' => ['esquema' => 'MGDEE-A-VALIDAR', 'version_expediente' => 1],
            ]])->save();
            $this->audit->registrar('expediente.crear_docdigital', 'expediente', $e->id, ['comunicacion' => $c->id], false, ['run' => null, 'rol' => 'sistema']);

            return $e;
        });
    }

    /** Un funcionario de la unidad receptora toma a su cargo un expediente de comunicación recibida. */
    public function asignarResponsable(Expediente $e, Usuario $u): Expediente
    {
        return DB::transaction(function () use ($e, $u) {
            $e = Expediente::query()->lockForUpdate()->findOrFail($e->id);
            if ($e->responsable_id !== null) {
                throw new FlujoException('El expediente ya tiene responsable.');
            }
            if (! $u->operaExpedientes() || $u->unidad_id !== $e->unidad_id) {
                throw new FlujoException('Solo funcionarios de la unidad receptora pueden asignarse el expediente.');
            }
            $m = $e->metadatos;
            $m['poderes']['responsable_run'] = $u->run;
            $e->forceFill(['responsable_id' => $u->id, 'metadatos' => $m])->save();
            $this->audit->registrar('expediente.asignar', 'expediente', $e->id);

            return $e;
        });
    }

    /** Metadatos del expediente (MGDEE). Nombres de campos A VALIDAR contra la Guía Técnica de Documentos y Expedientes. */
    public function mgdee(Expediente $e, Solicitud $s): array
    {
        $now = now('America/Santiago')->toIso8601String();

        return [
            'identificacion' => ['codigo' => $e->codigo, 'identificador_unico' => $this->identificador($e), 'institucion_codigo' => config('sgd.institucion_codigo'), 'nombre' => $s->tramite->nombre.' - '.$s->nombres.' '.$s->apellidos, 'organismo' => 'I. Municipalidad de Santo Domingo', 'unidad' => $e->unidad->nombre ?? null],
            'descripcion' => ['resumen' => mb_substr($s->detalle, 0, 500), 'folio_solicitud' => $s->folio],
            'temporalidad' => ['fecha_apertura' => $now, 'fecha_cierre' => null, 'plazo_dias' => $s->tramite->plazo_dias],
            'caracterizacion' => ['tipo_expediente' => 'Solicitud ciudadana', 'serie_documental' => $s->tramite->codigo_interno],
            'relaciones' => ['solicitud_id' => $s->id, 'expedientes_relacionados' => []],
            'poderes' => ['responsable_run' => null, 'representacion' => 'Titular'],
            'seguridad' => ['nivel_acceso' => 'reservado', 'datos_personales' => true],
            'procedimiento' => ['codigo_cpat' => $s->tramite->codigo_cpat, 'codigo_proceso' => $s->tramite->proceso_codigo, 'tramite' => $s->tramite->nombre],
            'version' => ['esquema' => 'MGDEE-A-VALIDAR', 'version_expediente' => 1],
        ];
    }

    public function actualizarMetadatos(Expediente $e, array $campos): Expediente
    {
        $m = $e->metadatos;
        $m['descripcion']['resumen'] = $campos['resumen'] ?? ($m['descripcion']['resumen'] ?? null);
        $m['caracterizacion']['serie_documental'] = $campos['serie'] ?? ($m['caracterizacion']['serie_documental'] ?? null);
        $m['seguridad']['nivel_acceso'] = $campos['nivel_acceso'] ?? ($m['seguridad']['nivel_acceso'] ?? 'reservado');
        $m['version']['version_expediente'] = ($m['version']['version_expediente'] ?? 1) + 1;
        $e->forceFill(['metadatos' => $m])->save();
        $this->audit->registrar('expediente.metadatos', 'expediente', $e->id);

        return $e;
    }

    /** Índice electrónico del expediente. */
    public function indice(Expediente $e, ?Usuario $u = null): array
    {
        $ocultos = $u ? app(AccesoRestringidoService::class)->ocultos($u, $e) : ['archivos' => [], 'documentos' => []];
        $items = [];
        foreach ($e->archivos()->get() as $a) {
            $items[] = in_array($a->id, $ocultos['archivos'], true)
                ? ['tipo' => 'archivo', 'titulo' => 'Archivo restringido', 'sha256' => null, 'fecha' => (string) $a->created_at, 'restringido' => true]
                : ['tipo' => 'archivo', 'titulo' => $a->titulo ?: $a->nombre_original, 'sha256' => $a->sha256, 'fecha' => (string) $a->created_at];
        }
        foreach ($e->documentos()->with('tipo')->get() as $d) {
            $items[] = in_array($d->id, $ocultos['documentos'], true)
                ? ['tipo' => 'documento', 'titulo' => 'Documento restringido', 'estado' => $d->estado, 'sha256' => null, 'fecha' => (string) $d->updated_at, 'restringido' => true]
                : ['tipo' => 'documento', 'titulo' => $d->titulo, 'estado' => $d->estado, 'numero' => $d->numeroFormateado(), 'sha256' => $d->pdf_sha256, 'fecha' => (string) $d->updated_at];
        }

        return $items;
    }

    public function exportar(Expediente $e, ?Usuario $u = null): array
    {
        $this->audit->registrar('expediente.exportar', 'expediente', $e->id, [], true);
        $e->loadMissing('documentos.tipo');
        $m = $e->metadatos;
        $m['indice'] = $this->indice($e, $u);
        $ocultos = $u ? app(AccesoRestringidoService::class)->ocultos($u, $e)['documentos'] : [];
        $m['documentos'] = $e->documentos->map(fn ($d) => in_array($d->id, $ocultos, true) ? ['identificacion' => ['id' => $d->id, 'nombre' => 'Documento restringido'], 'estado' => $d->estado] : ($d->metadatos ?? ['identificacion' => ['id' => $d->id, 'nombre' => $d->titulo], 'estado' => $d->estado]))->values()->all();

        return $m;
    }

    public function cerrar(Expediente $e): Expediente
    {
        return DB::transaction(function () use ($e) {
            $e = Expediente::query()->lockForUpdate()->findOrFail($e->id);
            if ($e->estado !== 'abierto') {
                throw new FlujoException('El expediente ya está cerrado.');
            }
            if ($e->documentos()->whereIn('estado', ['en_flujo'])->exists()) {
                throw new FlujoException('Hay documentos en flujo de firma; complételos o devuélvalos a borrador.');
            }
            $m = $e->metadatos;
            $m['temporalidad']['fecha_cierre'] = now('America/Santiago')->toIso8601String();
            $e->forceFill(['estado' => 'cerrado', 'cerrado_en' => now(), 'metadatos' => $m])->save();
            $e->solicitud?->forceFill(['estado' => 'finalizada'])->save();
            $this->audit->registrar('expediente.cerrar', 'expediente', $e->id);
            if ($e->solicitud) {
                $this->mov->registrar($e->solicitud, 'resuelta', 'La gestión de su solicitud terminó. Se le notificará la respuesta final.', $e->unidad_id);
            }

            return $e;
        });
    }

    public function reabrir(Expediente $e, string $motivo): Expediente
    {
        if ($e->estado !== 'cerrado') {
            throw new FlujoException('El expediente no está cerrado.');
        }
        $m = $e->metadatos;
        $m['temporalidad']['fecha_cierre'] = null;
        $e->forceFill(['estado' => 'abierto', 'cerrado_en' => null, 'motivo_reapertura' => $motivo, 'metadatos' => $m])->save();
        $e->solicitud?->forceFill(['estado' => 'en_tramite'])->save();
        $this->audit->registrar('expediente.reabrir', 'expediente', $e->id, ['motivo' => $motivo]);
        if ($e->solicitud) {
            $this->mov->registrar($e->solicitud, 'reabierta', 'La gestión de su solicitud se reabrió para completar antecedentes.', $e->unidad_id);
        }

        return $e;
    }
}
