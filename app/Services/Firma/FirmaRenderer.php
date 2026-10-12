<?php

namespace App\Services\Firma;

use App\Models\Documento;
use App\Models\FirmaEstilo;
use App\Models\FirmaPosicion;
use Illuminate\Support\Collection;

/**
 * Dibuja los sellos de firma de la copia pública según el estilo y la posición configurados (por tipo de documento y
 * por orden de firma). Los datos se escapan en la plantilla Blade. Nota: la apariencia visible de la firma PAdES dentro del
 * PDF firmado por FirmaGob la define FirmaGob; esta configuración gobierna el sello de la copia pública y de las pruebas.
 */
class FirmaRenderer
{
    /** @var Collection<int,FirmaPosicion>|null */
    private ?Collection $posiciones = null;

    private ?FirmaEstilo $defecto = null;

    public static function estiloIntegrado(): FirmaEstilo
    {
        return new FirmaEstilo(['nombre' => 'Integrado', 'texto_encabezado' => 'Firmado electrónicamente por', 'mostrar_nombre' => true, 'mostrar_cargo' => true, 'mostrar_run' => false,
            'mostrar_fecha' => true, 'mostrar_codigo' => true, 'color_borde' => '#006fb3', 'color_fondo' => '#ffffff', 'color_texto' => '#111111', 'tamano_fuente' => 8,
            'ancho_mm' => 70, 'alto_mm' => 28, 'borde_estilo' => 'solid', 'alineacion_texto' => 'left']);
    }

    public function estiloPorDefecto(): FirmaEstilo
    {
        return $this->defecto ??= (FirmaEstilo::query()->where('defecto', true)->where('activo', true)->first() ?? self::estiloIntegrado());
    }

    /** @return array{pos:?FirmaPosicion,estilo:FirmaEstilo,orden:int} */
    public function resolver(?int $tipoId, int $orden): array
    {
        $this->posiciones ??= FirmaPosicion::query()->with('estilo')->where('activo', true)->get();
        $pos = $this->posiciones->first(fn ($p) => $p->tipo_documento_id === $tipoId && $p->orden_firma === $orden)
            ?? $this->posiciones->first(fn ($p) => $p->tipo_documento_id === null && $p->orden_firma === $orden);
        $estilo = $pos?->estilo && $pos->estilo->activo ? $pos->estilo : $this->estiloPorDefecto();

        return ['pos' => $pos, 'estilo' => $estilo, 'orden' => $orden];
    }

    /** @return array{inicio:string,final:string} HTML de los sellos de un documento vigente. */
    public function html(Documento $d): array
    {
        $d->loadMissing(['pasos.usuario', 'tipo']);
        $items = [];
        $i = 0;
        foreach ($d->pasos->where('tipo', 'firma')->sortBy('orden') as $p) {
            $i++;
            $items[] = $this->resolver($d->tipo_documento_id, $i) + ['datos' => [
                'nombre' => $p->usuario->nombreCompleto(), 'cargo' => $p->usuario->cargo, 'run' => $p->usuario->run,
                'fecha' => $p->completado_en?->timezone('America/Santiago')->format('d-m-Y H:i'), 'codigo' => $d->codigo_validacion, 'simulada' => (bool) $p->simulada,
            ]];
        }

        return $this->componer($items);
    }

    /** Muestra con datos ficticios (vista previa y PDF de prueba). */
    public function muestra(?int $tipoId, int $firmas = 2): array
    {
        $items = [];
        for ($i = 1; $i <= $firmas; $i++) {
            $items[] = $this->resolver($tipoId, $i) + ['datos' => ['nombre' => $i === 1 ? 'Nombre Apellido (Alcalde)' : 'Nombre Apellido (Secretario/a)', 'cargo' => $i === 1 ? 'Alcalde' : 'Secretario(a) Municipal',
                'run' => '12345678-5', 'fecha' => now('America/Santiago')->format('d-m-Y H:i'), 'codigo' => 'SDD-EJEM-PL0A-BCDE', 'simulada' => false]];
        }

        return $this->componer($items);
    }

    /** @param array<int,array{pos:?FirmaPosicion,estilo:FirmaEstilo,orden:int,datos:array<string,mixed>}> $items */
    public function componer(array $items): array
    {
        $inicio = '';
        $filas = []; // filas de la franja final: [alineacion => html]
        foreach ($items as $it) {
            $bloque = view('pdf.firma_bloque', ['e' => $it['estilo'], 'f' => $it['datos']])->render();
            $pos = $it['pos'];
            if ($pos && $pos->pagina === 'primera' && $pos->x_mm !== null && $pos->y_mm !== null) {
                $inicio .= '<div style="position:absolute;left:'.(int) $pos->x_mm.'mm;top:'.(int) $pos->y_mm.'mm;width:'.(int) $it['estilo']->ancho_mm.'mm">'.$bloque.'</div>';

                continue;
            }
            $al = $pos->alineacion ?? ['izquierda', 'derecha', 'centro'][($it['orden'] - 1) % 3];
            $margen = (int) ($pos->margen_superior_mm ?? 12);
            $n = count($filas);
            if ($n === 0 || isset($filas[$n - 1]['celdas'][$al])) {
                $filas[] = ['celdas' => [], 'margen' => $margen];
                $n++;
            }
            $filas[$n - 1]['celdas'][$al] = $bloque;
            $filas[$n - 1]['margen'] = max($filas[$n - 1]['margen'], $margen);
        }

        return ['inicio' => $inicio, 'final' => $filas ? view('pdf.firma_franja', ['filas' => $filas])->render() : ''];
    }
}
