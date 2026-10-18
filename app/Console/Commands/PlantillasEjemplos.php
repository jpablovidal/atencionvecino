<?php

namespace App\Console\Commands;

use App\Models\Plantilla;
use App\Models\PlantillaVersion;
use App\Models\TipoDocumento;
use App\Support\PlantillaEjemplos;
use App\Support\PlantillaEstructura;
use Illuminate\Console\Command;

/** Crea (solo si faltan) las plantillas de ejemplo por secciones: decreto, oficio, ordenanza, reglamento e instructivo. Seguro para producción: no modifica las existentes. */
class PlantillasEjemplos extends Command
{
    protected $signature = 'plantillas:ejemplos {--inactivas : las crea desactivadas (para revisarlas antes de usarlas)}';

    protected $description = 'Crea (solo si faltan) las plantillas de ejemplo por secciones (decreto, oficio, ordenanza, reglamento, instructivo) y los tipos de documento que falten.';

    public function handle(): int
    {
        $creadas = 0;
        foreach (PlantillaEjemplos::definiciones() as $ej) {
            if (Plantilla::query()->where('nombre', $ej['nombre'])->exists()) {
                $this->line('Ya existe: '.$ej['nombre']);

                continue;
            }
            $tipo = TipoDocumento::query()->firstOrCreate(['codigo' => $ej['tipo_codigo']], ['nombre' => $ej['tipo_nombre'], 'prefijo' => $ej['prefijo'], 'firma_alcalde' => $ej['tipo_codigo'] === 'DEC_ALC', 'firmas_agil' => 1, 'firmas_clasico' => 2, 'activo' => true]);
            $html = PlantillaEstructura::componer($ej['estructura']);
            $p = Plantilla::query()->create(['tipo_documento_id' => $tipo->id, 'nombre' => $ej['nombre'], 'descripcion' => $ej['descripcion'], 'contenido_html' => $html, 'estructura' => $ej['estructura'],
                'alcance' => 'todas', 'activo' => ! $this->option('inactivas'), 'version' => 1]);
            PlantillaVersion::query()->create(['plantilla_id' => $p->id, 'version' => 1, 'nombre' => $p->nombre, 'contenido_html' => $html, 'estructura' => $ej['estructura'], 'sha256' => hash('sha256', $html), 'created_at' => now()]);
            $this->info('Creada: '.$ej['nombre']);
            $creadas++;
        }
        $this->info("Listo: $creadas plantilla(s) de ejemplo creadas.");

        return self::SUCCESS;
    }
}
