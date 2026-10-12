<?php

namespace App\Services\Firma;

use App\Exceptions\FlujoException;
use App\Models\FirmaEstilo;
use App\Models\FirmaPosicion;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Contraste;
use Illuminate\Support\Facades\DB;

/** Mantenedores de estilo y posición de firmas, con validaciones de legibilidad y de límites de la página (carta). */
class FirmaConfigService
{
    public const PAGINA_ANCHO_MM = 215;

    public const PAGINA_ALTO_MM = 279;

    public function __construct(private readonly AuditoriaService $audit) {}

    /** @param array<string,mixed> $d */
    public function guardarEstilo(array $d, ?FirmaEstilo $e, Usuario $u): FirmaEstilo
    {
        foreach (['color_borde', 'color_fondo', 'color_texto'] as $c) {
            if (! Contraste::esHex((string) ($d[$c] ?? ''))) {
                throw new FlujoException('Los colores deben tener el formato #RRGGBB.');
            }
        }
        if (Contraste::ratio($d['color_texto'], $d['color_fondo']) < 4.5) {
            throw new FlujoException('El texto del sello no es legible sobre el fondo (contraste '.number_format(Contraste::ratio($d['color_texto'], $d['color_fondo']), 1, ',', '').':1; mínimo 4,5:1).');
        }
        if (! in_array($d['borde_estilo'], ['solid', 'dashed', 'dotted', 'none'], true) || ! in_array($d['alineacion_texto'], ['left', 'center'], true)) {
            throw new FlujoException('Opción de borde o alineación inválida.');
        }
        if ($d['tamano_fuente'] < 6 || $d['tamano_fuente'] > 14 || $d['ancho_mm'] < 40 || $d['ancho_mm'] > 120 || $d['alto_mm'] < 15 || $d['alto_mm'] > 60) {
            throw new FlujoException('Tamaño de fuente (6–14 pt), ancho (40–120 mm) o alto (15–60 mm) fuera de rango.');
        }
        $texto = trim((string) $d['texto_encabezado']);
        if ($texto === '' || mb_strlen($texto) > 80 || preg_match('/[\r\n]/', $texto)) {
            throw new FlujoException('El texto de encabezado debe tener entre 1 y 80 caracteres, en una línea.');
        }
        if (! ($d['mostrar_nombre'] ?? false) && ! ($d['mostrar_codigo'] ?? false)) {
            throw new FlujoException('El sello debe mostrar al menos el nombre del firmante o el código de validación.');
        }
        $campos = ['nombre' => trim($d['nombre']), 'texto_encabezado' => $texto, 'color_borde' => strtolower($d['color_borde']), 'color_fondo' => strtolower($d['color_fondo']), 'color_texto' => strtolower($d['color_texto']),
            'tamano_fuente' => (int) $d['tamano_fuente'], 'ancho_mm' => (int) $d['ancho_mm'], 'alto_mm' => (int) $d['alto_mm'], 'borde_estilo' => $d['borde_estilo'], 'alineacion_texto' => $d['alineacion_texto']];
        foreach (['mostrar_nombre', 'mostrar_cargo', 'mostrar_run', 'mostrar_fecha', 'mostrar_codigo'] as $b) {
            $campos[$b] = (bool) ($d[$b] ?? false);
        }
        if ($campos['nombre'] === '' || mb_strlen($campos['nombre']) > 80) {
            throw new FlujoException('El nombre del estilo es obligatorio (máx. 80 caracteres).');
        }
        if (FirmaEstilo::query()->where('nombre', $campos['nombre'])->when($e, fn ($q) => $q->where('id', '!=', $e->id))->exists()) {
            throw new FlujoException('Ya existe un estilo con ese nombre.');
        }
        $e = DB::transaction(function () use ($e, $campos) {
            $e ??= new FirmaEstilo(['activo' => true, 'defecto' => false]);
            $e->fill($campos)->save();

            return $e;
        });
        $this->audit->registrar('firmas.estilo_guardar', 'firma_estilo', $e->id);

        return $e;
    }

    public function definirEstiloDefecto(FirmaEstilo $e): void
    {
        if (! $e->activo) {
            throw new FlujoException('Un estilo inactivo no puede ser el estilo por defecto.');
        }
        DB::transaction(function () use ($e) {
            FirmaEstilo::query()->where('id', '!=', $e->id)->update(['defecto' => false]);
            $e->forceFill(['defecto' => true])->save();
        });
        $this->audit->registrar('firmas.estilo_defecto', 'firma_estilo', $e->id);
    }

    public function alternarEstilo(FirmaEstilo $e): void
    {
        if ($e->activo && $e->defecto) {
            throw new FlujoException('No se puede desactivar el estilo por defecto: elija otro antes.');
        }
        $e->forceFill(['activo' => ! $e->activo])->save();
        $this->audit->registrar('firmas.estilo_activo', 'firma_estilo', $e->id, ['activo' => $e->activo]);
    }

    /** @param array<string,mixed> $d */
    public function guardarPosicion(array $d, ?FirmaPosicion $p, Usuario $u): FirmaPosicion
    {
        $nombre = trim((string) $d['nombre']);
        if ($nombre === '' || mb_strlen($nombre) > 80) {
            throw new FlujoException('El nombre es obligatorio (máx. 80 caracteres).');
        }
        if (! in_array($d['pagina'], ['ultima', 'primera'], true) || ! in_array($d['alineacion'], ['izquierda', 'centro', 'derecha'], true) || $d['orden_firma'] < 1 || $d['orden_firma'] > 3) {
            throw new FlujoException('Posición inválida.');
        }
        $estilo = ! empty($d['estilo_id']) ? FirmaEstilo::query()->where('activo', true)->find($d['estilo_id']) : null;
        if (! empty($d['estilo_id']) && ! $estilo) {
            throw new FlujoException('El estilo elegido no existe o está inactivo.');
        }
        $ancho = (int) ($estilo->ancho_mm ?? FirmaEstilo::query()->where('defecto', true)->value('ancho_mm') ?? 70);
        $alto = (int) ($estilo->alto_mm ?? FirmaEstilo::query()->where('defecto', true)->value('alto_mm') ?? 28);
        $x = $y = null;
        if ($d['pagina'] === 'primera') {
            if (($d['x_mm'] ?? '') === '' || ($d['y_mm'] ?? '') === '') {
                throw new FlujoException('Para la primera página indique las coordenadas X e Y en milímetros.');
            }
            [$x, $y] = [(int) $d['x_mm'], (int) $d['y_mm']];
            if ($x < 0 || $y < 0 || $x + $ancho > self::PAGINA_ANCHO_MM || $y + $alto > self::PAGINA_ALTO_MM) {
                throw new FlujoException("El sello ({$ancho}×{$alto} mm) quedaría fuera de la página carta (".self::PAGINA_ANCHO_MM.'×'.self::PAGINA_ALTO_MM.' mm).');
            }
        }
        $margen = (int) $d['margen_superior_mm'];
        if ($margen < 0 || $margen > 60) {
            throw new FlujoException('El margen superior debe estar entre 0 y 60 mm.');
        }
        $tipo = ! empty($d['tipo_documento_id']) ? (int) $d['tipo_documento_id'] : null;
        $activo = (bool) ($d['activo'] ?? true);
        if ($activo && FirmaPosicion::query()->where('activo', true)->where('orden_firma', (int) $d['orden_firma'])->where('tipo_documento_id', $tipo)->when($p, fn ($q) => $q->where('id', '!=', $p->id))->exists()) {
            throw new FlujoException('Ya hay una posición activa para ese tipo de documento y ese orden de firma. Desactívela o edítela.');
        }
        $p ??= new FirmaPosicion;
        $p->fill(['nombre' => $nombre, 'tipo_documento_id' => $tipo, 'orden_firma' => (int) $d['orden_firma'], 'pagina' => $d['pagina'], 'alineacion' => $d['alineacion'],
            'margen_superior_mm' => $margen, 'x_mm' => $x, 'y_mm' => $y, 'estilo_id' => $estilo?->id, 'activo' => $activo])->save();
        $this->audit->registrar('firmas.posicion_guardar', 'firma_posicion', $p->id);

        return $p;
    }

    public function alternarPosicion(FirmaPosicion $p): void
    {
        if (! $p->activo && FirmaPosicion::query()->where('activo', true)->where('orden_firma', $p->orden_firma)->where('tipo_documento_id', $p->tipo_documento_id)->where('id', '!=', $p->id)->exists()) {
            throw new FlujoException('Ya hay otra posición activa para ese tipo y orden de firma.');
        }
        $p->forceFill(['activo' => ! $p->activo])->save();
        $this->audit->registrar('firmas.posicion_activo', 'firma_posicion', $p->id, ['activo' => $p->activo]);
    }
}
