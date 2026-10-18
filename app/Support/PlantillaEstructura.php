<?php

namespace App\Support;

use App\Exceptions\FlujoException;
use App\Models\Usuario;
use App\Services\HtmlSanitizer;
use App\Services\TramiteService;

/**
 * Plantilla dividida en secciones: materia, vistos, considerando, cuerpo (decreto, reglamento, ordenanza, instructivo u oficio),
 * firmas y cadena de responsabilidad (cargos que deben visar; sus iniciales se insertan en el documento).
 * Esta clase valida la estructura y la compone en el HTML de la plantilla (función pura, sin acceso a la base de datos).
 */
class PlantillaEstructura
{
    public const TIPOS_CUERPO = ['decreto' => 'Decreto', 'reglamento' => 'Reglamento', 'ordenanza' => 'Ordenanza', 'instructivo' => 'Instructivo', 'oficio' => 'Oficio'];

    public const ROLES_FIRMA = ['alcalde' => 'Alcalde(sa)', 'secretario' => 'Secretario(a) municipal', 'funcionario' => 'Funcionario(a) o director(a)', 'otro' => 'Otro cargo'];

    /** Cierre por defecto según el tipo de cuerpo. */
    public const CIERRES = ['decreto' => 'ANÓTESE, COMUNÍQUESE Y ARCHÍVESE.', 'reglamento' => 'ANÓTESE, COMUNÍQUESE Y ARCHÍVESE.', 'ordenanza' => 'ANÓTESE, COMUNÍQUESE, PUBLÍQUESE Y ARCHÍVESE.',
        'instructivo' => 'Se instruye dar estricto cumplimiento a lo dispuesto.', 'oficio' => 'Saluda atentamente a Ud.,'];

    private const MAX_ITEMS = 30;

    private static function texto(mixed $t, int $min, int $max, string $campo): string
    {
        $t = TramiteService::limpiarTexto(is_string($t) ? $t : '');
        if (mb_strlen($t) < $min || mb_strlen($t) > $max) {
            throw new FlujoException("{$campo}: entre {$min} y {$max} caracteres.");
        }

        return $t;
    }

    /** @return array<int,string> */
    private static function lista(mixed $v, string $campo): array
    {
        $out = [];
        foreach ((array) $v as $x) {
            $x = TramiteService::limpiarTexto(is_string($x) ? $x : '');
            if ($x === '') {
                continue;
            }
            if (mb_strlen($x) > 1000) {
                throw new FlujoException("{$campo}: cada ítem admite hasta 1000 caracteres.");
            }
            $out[] = $x;
        }
        if (count($out) > self::MAX_ITEMS) {
            throw new FlujoException("{$campo}: máximo ".self::MAX_ITEMS.' ítems.');
        }

        return $out;
    }

    /**
     * Valida y normaliza la estructura recibida del formulario.
     *
     * @param  array<string,mixed>  $in
     * @return array{materia:string,vistos:array<int,string>,considerando:array<int,string>,cuerpo_tipo:string,cuerpo_html:string,cierre:string,firmas:array<int,array{cargo:string,rol:string}>,cadena:array<int,array{cargo:string,usuario_id:?int}>}
     */
    public static function normalizar(array $in, HtmlSanitizer $san): array
    {
        $tipo = (string) ($in['cuerpo_tipo'] ?? '');
        if (! isset(self::TIPOS_CUERPO[$tipo])) {
            throw new FlujoException('Elija el tipo de cuerpo: decreto, reglamento, ordenanza, instructivo u oficio.');
        }
        $cuerpo = $san->limpiar((string) ($in['cuerpo_html'] ?? ''));
        if (trim(strip_tags($cuerpo, '<img><table><hr>')) === '') {
            throw new FlujoException('El cuerpo no puede estar vacío.');
        }
        $cierre = trim((string) ($in['cierre'] ?? '')) === '' ? self::CIERRES[$tipo] : self::texto($in['cierre'], 3, 200, 'El cierre');
        $firmas = [];
        foreach ((array) ($in['firmas'] ?? []) as $f) {
            if (trim((string) ($f['cargo'] ?? '')) === '') {
                continue;
            }
            $rol = (string) ($f['rol'] ?? 'otro');
            if (! isset(self::ROLES_FIRMA[$rol])) {
                throw new FlujoException('Rol de firmante inválido.');
            }
            $firmas[] = ['cargo' => self::texto($f['cargo'], 3, 120, 'El cargo del firmante'), 'rol' => $rol];
        }
        if (! $firmas) {
            throw new FlujoException('Indique al menos una firma (cargo de quien firma).');
        }
        if (count($firmas) > 6) {
            throw new FlujoException('Máximo 6 firmas.');
        }
        $cadena = [];
        foreach ((array) ($in['cadena'] ?? []) as $c) {
            if (trim((string) ($c['cargo'] ?? '')) === '') {
                continue;
            }
            $uid = isset($c['usuario_id']) && $c['usuario_id'] !== '' ? (int) $c['usuario_id'] : null;
            if ($uid !== null && ! Usuario::query()->where('activo', true)->whereKey($uid)->exists()) {
                throw new FlujoException('El usuario titular de la cadena de responsabilidad no existe o está inactivo.');
            }
            $cadena[] = ['cargo' => self::texto($c['cargo'], 3, 120, 'El cargo de la cadena de responsabilidad'), 'usuario_id' => $uid];
        }
        if (count($cadena) > 10) {
            throw new FlujoException('Máximo 10 cargos en la cadena de responsabilidad.');
        }

        return ['materia' => self::texto($in['materia'] ?? '', 5, 300, 'La materia'), 'vistos' => self::lista($in['vistos'] ?? [], 'Vistos'), 'considerando' => self::lista($in['considerando'] ?? [], 'Considerando'),
            'cuerpo_tipo' => $tipo, 'cuerpo_html' => $cuerpo, 'cierre' => $cierre, 'firmas' => $firmas, 'cadena' => $cadena];
    }

    /** HTML de la plantilla a partir de la estructura (los textos se escapan; los campos {{…}} se conservan). */
    public static function componer(array $e): string
    {
        $tipo = $e['cuerpo_tipo'];
        $oficio = $tipo === 'oficio';
        $h = '<p style="text-align:right">Santo Domingo, {{fecha}}</p>';
        $h .= '<p><strong>'.($oficio ? 'MAT.' : 'MATERIA').':</strong> '.e($e['materia']).'</p>';
        $items = fn (array $l) => '<ol>'.implode('', array_map(fn ($x) => '<li>'.e($x).'</li>', $l)).'</ol>';
        if ($e['vistos']) {
            $h .= '<p><strong>'.($oficio ? 'ANT.' : 'VISTOS').':</strong></p>'.$items($e['vistos']);
        }
        if ($e['considerando']) {
            $h .= '<p><strong>CONSIDERANDO:</strong></p>'.$items($e['considerando']);
        }
        $h .= '<p><strong>'.mb_strtoupper(self::TIPOS_CUERPO[$tipo]).($oficio ? '' : ':').'</strong></p>'.$e['cuerpo_html'];
        $h .= '<p>'.e($e['cierre']).'</p>';
        $n = max(1, count($e['firmas']));
        $celdas = implode('', array_map(fn ($f) => '<td style="text-align:center;width:'.floor(100 / $n).'%"><p>&nbsp;</p><p>&nbsp;</p><p><strong>'.e(mb_strtoupper($f['cargo'])).'</strong></p></td>', $e['firmas']));
        $h .= '<table style="width:100%;border-collapse:collapse;margin-top:24px"><tbody><tr>'.$celdas.'</tr></tbody></table>';
        if ($e['cadena']) {
            $filas = '';
            foreach ($e['cadena'] as $i => $c) {
                $filas .= '<tr><td style="border:1px solid #999;padding:2px 6px">'.e($c['cargo']).'</td><td style="border:1px solid #999;padding:2px 6px;text-align:center">{{cadena_'.($i + 1).'}}</td></tr>';
            }
            $h .= '<p style="font-size:9pt;margin-top:18px"><strong>Cadena de responsabilidad (visaciones):</strong> {{cadena_iniciales}}</p>';
            $h .= '<table style="font-size:9pt;border-collapse:collapse;border:1px solid #999;width:60%"><thead><tr><th style="border:1px solid #999;padding:2px 6px;text-align:left">Cargo</th><th style="border:1px solid #999;padding:2px 6px">Iniciales</th></tr></thead><tbody>'.$filas.'</tbody></table>';
        } else {
            $h .= '<p style="font-size:9pt;margin-top:18px">Elaboró: {{autor_iniciales}}</p>';
        }

        return $h;
    }

    /** Iniciales de una persona: primer nombre y los dos apellidos (p. ej. «María José Pérez Soto» → «MPS»). */
    public static function iniciales(Usuario $u): string
    {
        $n = preg_split('/\s+/u', trim($u->nombres)) ?: [];
        $a = preg_split('/\s+/u', trim($u->apellidos)) ?: [];
        $letras = array_filter([$n[0] ?? '', $a[0] ?? '', $a[1] ?? ''], fn ($x) => $x !== '');

        return mb_strtoupper(implode('', array_map(fn ($x) => mb_substr($x, 0, 1), $letras)));
    }

    /**
     * Usuarios de la cadena: el titular indicado o, si no hay, la primera persona activa que opera expedientes con ese cargo.
     *
     * @param  array<int,array{cargo:string,usuario_id:?int}>  $cadena
     * @return array<int,?Usuario>
     */
    public static function resolverCadena(array $cadena): array
    {
        $out = [];
        foreach ($cadena as $c) {
            $u = null;
            if (! empty($c['usuario_id'])) {
                $u = Usuario::query()->where('activo', true)->find($c['usuario_id']);
            }
            $u ??= Usuario::query()->where('activo', true)->whereRaw('lower(cargo) = ?', [mb_strtolower($c['cargo'])])->orderBy('id')->get()->first(fn ($x) => $x->operaExpedientes());
            $out[] = $u;
        }

        return $out;
    }

    /**
     * Valores de combinación de la cadena: autor_iniciales, cadena_iniciales y cadena_N (quien no se puede determinar queda «—»).
     *
     * @return array<string,string>
     */
    public static function valoresCadena(?array $estructura, ?Usuario $autor): array
    {
        $v = ['autor_iniciales' => $autor ? self::iniciales($autor) : '—'];
        $ini = $autor ? [self::iniciales($autor)] : [];
        foreach (self::resolverCadena($estructura['cadena'] ?? []) as $i => $u) {
            $v['cadena_'.($i + 1)] = $u ? self::iniciales($u) : '—';
            if ($u && ! in_array(self::iniciales($u), $ini, true)) {
                $ini[] = self::iniciales($u);
            }
        }
        $v['cadena_iniciales'] = $ini ? implode('/', $ini) : '—';

        return $v;
    }

    /**
     * Sugerencia de flujo: visadores (cadena) y firmantes (firmas) como ids de usuario, según titular, rol o cargo.
     *
     * @return array{visadores:array<int,int>,firmantes:array<int,int>}
     */
    public static function sugerirFlujo(array $estructura, ?Usuario $autor = null): array
    {
        $vis = [];
        foreach (self::resolverCadena($estructura['cadena'] ?? []) as $u) {
            if ($u && (! $autor || $u->id !== $autor->id) && ! in_array($u->id, $vis, true)) {
                $vis[] = $u->id;
            }
        }
        $fir = [];
        foreach ($estructura['firmas'] ?? [] as $f) {
            $q = Usuario::query()->where('activo', true)->where('puede_firmar', true)->orderBy('id')->get()->filter(fn ($x) => $x->operaExpedientes());
            $u = match ($f['rol']) {
                'alcalde', 'secretario' => $q->first(fn ($x) => $x->rolBase() === $f['rol']),
                default => $q->first(fn ($x) => mb_strtolower((string) $x->cargo) === mb_strtolower($f['cargo'])),
            };
            if ($u && ! in_array($u->id, $fir, true) && ! in_array($u->id, $vis, true)) {
                $fir[] = $u->id;
            }
        }

        return ['visadores' => $vis, 'firmantes' => $fir];
    }
}
