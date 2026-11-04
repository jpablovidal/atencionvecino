<?php

namespace App\Console\Commands;

use App\Models\Plantilla;
use App\Models\Usuario;
use App\Services\PlantillaService;
use App\Support\PlantillaEstructura;
use Illuminate\Console\Command;

/**
 * Pone las plantillas estructuradas existentes al estilo vigente de los decretos alcaldicios (bloque REF./fecha a la derecha, títulos centrados, distribución). Solo
 * toca las que se generan a partir de sus secciones y cuyo HTML cambia; guarda una versión nueva de cada una. Idempotente: seguro para producción.
 */
class PlantillasEstilo extends Command
{
    protected $signature = 'plantillas:estilo';

    protected $description = 'Recompone las plantillas estructuradas con el estilo de los decretos alcaldicios (crea una versión nueva de cada una que cambie).';

    public function handle(PlantillaService $svc): int
    {
        $actor = Usuario::query()->where('activo', true)->where('rol', 'admin')->orderBy('id')->first() ?? Usuario::query()->where('activo', true)->orderBy('id')->first();
        if (! $actor) {
            $this->warn('No hay usuarios: nada que hacer.');

            return self::SUCCESS;
        }
        $n = 0;
        foreach (Plantilla::query()->whereNotNull('estructura')->get() as $p) {
            $e = (array) $p->estructura;
            if (! isset($e['cuerpo_tipo'], $e['materia'], $e['cierre'])) {
                continue;
            }
            try {
                $nuevo = PlantillaEstructura::componer($e);
            } catch (\Throwable) {
                continue;
            }
            if ($nuevo === $p->contenido_html) {
                continue;
            }
            $svc->guardar($p, ['nombre' => $p->nombre, 'tipo_documento_id' => $p->tipo_documento_id, 'descripcion' => $p->descripcion, 'contenido_html' => $nuevo, 'estructura' => $e,
                'alcance' => $p->alcance, 'unidades' => $p->unidades()->pluck('unidades.id')->all()], $actor);
            $this->line('Actualizada: '.$p->nombre);
            $n++;
        }
        $this->info($n === 0 ? 'Todas las plantillas estructuradas ya tienen el estilo vigente.' : "$n plantilla(s) actualizada(s).");

        return self::SUCCESS;
    }
}
