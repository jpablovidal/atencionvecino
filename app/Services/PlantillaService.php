<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Plantilla;
use App\Models\PlantillaVersion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/** Formatos y plantillas: guardado versionado, visibilidad por unidad, duplicado, restauración y vista previa. */
class PlantillaService
{
    /** Campos de combinación disponibles: nombre => [descripción, ejemplo para vistas previas]. Deben coincidir con DocumentoService::combinar. */
    public const VARIABLES = [
        'fecha' => ['Fecha de emisión (dd-mm-aaaa)', '15-03-2026'],
        'expediente' => ['Código del expediente', 'EXP-2026-000123'],
        'folio' => ['Folio de la solicitud', 'SOL-2026-000045'],
        'solicitante_nombre' => ['Nombre del solicitante', 'María Pérez González'],
        'solicitante_run' => ['RUN del solicitante', '12.345.678-5'],
        'tramite' => ['Trámite o servicio', 'Permiso de edificación'],
        'unidad' => ['Unidad a cargo del expediente', 'Dirección de Obras Municipales'],
        'institucion_origen' => ['Institución de origen (DocDigital)', 'Servicio de Salud'],
        'asunto_origen' => ['Asunto de la comunicación recibida', 'Solicitud de información'],
        'folio_docdigital' => ['Folio DocDigital', 'DD-2026-98765'],
    ];

    public function __construct(private readonly HtmlSanitizer $san, private readonly AuditoriaService $audit) {}

    /**
     * @param  array{nombre:string,tipo_documento_id:int,descripcion?:?string,contenido_html:string,alcance:string,unidades?:array<int,int>}  $d
     */
    public function guardar(?Plantilla $p, array $d, Usuario $u): Plantilla
    {
        $html = $this->san->limpiar($d['contenido_html']);
        if (trim(strip_tags($html, '<img><table><hr>')) === '') {
            throw new FlujoException('La plantilla no puede estar vacía.');
        }
        $alcance = $d['alcance'] === 'unidades' ? 'unidades' : 'todas';
        $unidades = array_values(array_unique(array_map('intval', $d['unidades'] ?? [])));
        if ($alcance === 'unidades' && $unidades === []) {
            throw new FlujoException('Indique al menos una unidad que podrá usar este formato, o elija «Toda la municipalidad».');
        }

        return DB::transaction(function () use ($p, $d, $html, $alcance, $unidades, $u) {
            $nombre = trim($d['nombre']);
            $cambio = ! $p || $p->contenido_html !== $html || $p->nombre !== $nombre;
            $datos = ['tipo_documento_id' => $d['tipo_documento_id'], 'nombre' => $nombre, 'descripcion' => ($d['descripcion'] ?? null) ?: null,
                'contenido_html' => $html, 'alcance' => $alcance, 'actualizado_por' => $u->id];
            if ($p) {
                $nuevaVersion = $cambio ? $p->version + 1 : $p->version;
                $p->update($datos + ['version' => $nuevaVersion]);
            } else {
                $p = Plantilla::query()->create($datos + ['activo' => true, 'version' => 1]);
            }
            if ($cambio) {
                PlantillaVersion::query()->create(['plantilla_id' => $p->id, 'version' => $p->version, 'nombre' => $nombre, 'contenido_html' => $html,
                    'sha256' => hash('sha256', $html), 'creado_por' => $u->id, 'created_at' => now()]);
            }
            $p->unidades()->sync($alcance === 'unidades' ? $unidades : []);
            $this->audit->registrar('plantilla.guardar', 'plantilla', $p->id, ['version' => $p->version, 'alcance' => $alcance, 'unidades' => count($unidades)]);

            return $p->refresh();
        });
    }

    public function duplicar(Plantilla $p, Usuario $u): Plantilla
    {
        $copia = $this->guardar(null, [
            'nombre' => mb_substr('Copia de '.$p->nombre, 0, 150), 'tipo_documento_id' => $p->tipo_documento_id, 'descripcion' => $p->descripcion,
            'contenido_html' => $p->contenido_html, 'alcance' => $p->alcance, 'unidades' => $p->unidades()->pluck('unidades.id')->all(),
        ], $u);
        $copia->update(['activo' => false]); // la copia nace inactiva hasta que se revise
        $this->audit->registrar('plantilla.duplicar', 'plantilla', $copia->id, ['origen' => $p->id]);

        return $copia;
    }

    public function restaurar(Plantilla $p, int $version, Usuario $u): Plantilla
    {
        $v = PlantillaVersion::query()->where('plantilla_id', $p->id)->where('version', $version)->firstOrFail();
        $this->audit->registrar('plantilla.restaurar', 'plantilla', $p->id, ['desde_version' => $version]);

        return $this->guardar($p, [
            'nombre' => $v->nombre, 'tipo_documento_id' => $p->tipo_documento_id, 'descripcion' => $p->descripcion, 'contenido_html' => $v->contenido_html,
            'alcance' => $p->alcance, 'unidades' => $p->unidades()->pluck('unidades.id')->all(),
        ], $u);
    }

    /** ¿Puede este usuario usar/ver la plantilla? (visibilidad por unidad) */
    public function visible(Plantilla $p, Usuario $u): bool
    {
        return Plantilla::query()->visiblesPara($u)->where('id', $p->id)->exists();
    }

    /** Contenido con datos de ejemplo (valores escapados). */
    public function conMuestra(string $html): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => isset(self::VARIABLES[$m[1]]) ? e(self::VARIABLES[$m[1]][1]) : $m[0], $html) ?? $html;
    }
}
