<?php

namespace App\Console\Commands;

use App\Support\NormasTecnicas;
use Illuminate\Console\Command;

class NormasDocumento extends Command
{
    protected $signature = 'normas:documento {--comprobar : Solo verifica que docs/NORMAS_TECNICAS.md esté al día}';

    protected $description = 'Genera docs/NORMAS_TECNICAS.md (matriz de cumplimiento de las normas técnicas) desde App\Support\NormasTecnicas.';

    public function handle(): int
    {
        $md = self::markdown();
        $ruta = base_path('docs/NORMAS_TECNICAS.md');
        if ($this->option('comprobar')) {
            $ok = is_file($ruta) && file_get_contents($ruta) === $md;
            $this->line($ok ? 'docs/NORMAS_TECNICAS.md está al día.' : 'docs/NORMAS_TECNICAS.md está desactualizado: ejecute php artisan normas:documento');

            return $ok ? self::SUCCESS : self::FAILURE;
        }
        file_put_contents($ruta, $md);
        $this->info('Generado docs/NORMAS_TECNICAS.md');

        return self::SUCCESS;
    }

    public static function markdown(): string
    {
        $e = NormasTecnicas::ESTADOS;
        $o = "# Normas técnicas de la Ley 21.180 — matriz de cumplimiento\n\n";
        $o .= "> Generado desde `App\\Support\\NormasTecnicas` (`php artisan normas:documento`); no edite este archivo a mano. La misma matriz se ve en el sistema en **Administración › Cumplimiento normativo**.\n\n";
        $o .= "**Qué se revisó.** Los Decretos 7, 8, 9, 10, 11 y 12 (de 2023, publicados el 17-08-2023) se leyeron en su texto oficial (Biblioteca del Congreso Nacional, versión del 21-10-2026; el Decreto 12 en su última versión, del 24-12-2025, modificada por el Decreto 876). **No se entregaron las guías técnicas** que cada norma ordena dictar a la División de Gobierno Digital: lo que depende de ellas (nombres exactos de metadatos, formatos, contratos de API) sigue **A VALIDAR**.\n\n";
        $o .= "## Normas\n| Decreto | Norma | Publicación | Versión | Texto oficial |\n|---|---|---|---|---|\n";
        foreach (NormasTecnicas::decretos() as $d) {
            $o .= "| {$d['numero']} | {$d['titulo']} | {$d['publicacion']} | {$d['version']} | ".($d['bcn'] ? "[BCN]({$d['bcn']})" : '—')." · [Guías]({$d['wiki']}) |\n";
        }
        $o .= "\n## Resumen\n";
        foreach (NormasTecnicas::resumen() as $k => $n) {
            $o .= "- {$e[$k]}: **$n**\n";
        }
        $o .= "\n**Estados.** *Implementado*: lo hace el sistema y tiene pruebas automáticas. *Parcial*: lo hace en parte. *Pendiente*: el sistema no lo hace. *Del municipio*: lo define o decide la institución. *Componente externo*: lo provee Gobierno Digital u otro órgano.\n";
        $o .= "\n**Plazo importante.** ".NormasTecnicas::PISEE_FIN.": termina la versión anterior de PISEE (Decreto 12, artículo segundo transitorio); desde entonces la red de interoperabilidad es la única vía.\n";
        $actual = null;
        foreach (NormasTecnicas::matriz() as $f) {
            if ($f['decreto'] !== $actual) {
                $actual = $f['decreto'];
                $d = NormasTecnicas::decretos()[$actual];
                $o .= "\n## Decreto {$actual} — {$d['titulo']}\n| Art. | Requisito | Estado | Qué hace el sistema / qué falta | Dónde |\n|---|---|---|---|---|\n";
            }
            $cel = fn (string $t) => str_replace('|', '\\|', $t);
            $o .= "| {$f['art']} | {$cel($f['requisito'])} | {$e[$f['estado']]} | {$cel($f['sistema'])} | {$cel($f['donde'])} |\n";
        }
        $o .= "\n## Cómo se mantiene\n1. Al recibir las guías técnicas, actualizar los nombres de metadatos, formatos y contratos marcados *A VALIDAR* y cambiar el estado de las filas.\n2. Revisar la matriz al menos cada dos años (cada decreto lo exige) y cuando cambie una norma.\n3. Regenerar este documento con `php artisan normas:documento`.\n";

        return $o;
    }
}
