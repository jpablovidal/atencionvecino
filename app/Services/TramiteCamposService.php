<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\ListaOpciones;
use App\Models\Tramite;
use App\Models\TramiteCampo;
use App\Support\Formula;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Formulario propio de cada trámite: define los campos (texto, número, fecha, listas, sí/no, cálculos), los valida al guardar el trámite y valida y calcula
 * las respuestas de cada solicitud. Los textos son planos (sin etiquetas ni enlaces); las fórmulas las analiza App\Support\Formula (sin eval).
 */
class TramiteCamposService
{
    public const MAX_CAMPOS = 40;

    public const MAX_OPCIONES = 200;

    /** Nombres que no pueden usarse como clave de un campo (funciones y constantes de las fórmulas). */
    private const RESERVADAS = ['hoy', 'si', 'min', 'max', 'abs', 'redondear', 'piso', 'techo', 'raiz', 'suma', 'promedio', 'potencia', 'dias', 'anios'];

    /** Tipos cuyo valor puede usarse dentro de una fórmula. */
    private const NUMERICOS = ['numero', 'fecha', 'si_no', 'lista', 'calculo'];

    public function __construct(private readonly AuditoriaService $audit) {}

    // ---------------------------------------------------------------- definición (al guardar el trámite)

    /**
     * Valida las filas del editor y las deja normalizadas. Lanza FlujoException con el primer problema.
     *
     * @param  array<int,mixed>  $filas
     * @return list<array<string,mixed>> [id, clave, etiqueta, tipo, ayuda, obligatorio, config]
     */
    public function normalizar(array $filas): array
    {
        $out = [];
        $claves = [];
        foreach ($filas as $f) {
            $f = (array) $f;
            if (! empty($f['eliminar']) || (trim((string) ($f['etiqueta'] ?? '')) === '' && trim((string) ($f['clave'] ?? '')) === '')) {
                continue; // fila vacía o quitada
            }
            $tipo = (string) ($f['tipo'] ?? '');
            if (! isset(TramiteCampo::TIPOS[$tipo])) {
                throw new FlujoException('Elija el tipo de cada campo.');
            }
            $etiqueta = TramiteService::limpiarTexto($f['etiqueta'] ?? '');
            if (mb_strlen($etiqueta) < 2 || mb_strlen($etiqueta) > 190 || TramiteService::contieneDireccionWeb($etiqueta)) {
                throw new FlujoException('El nombre de un campo debe tener entre 2 y 190 caracteres y no contener direcciones web: «'.mb_substr($etiqueta, 0, 40).'».');
            }
            $clave = $this->clave((string) ($f['clave'] ?? ''), $etiqueta, $claves);
            $claves[$clave] = true;
            $ayuda = TramiteService::limpiarTexto($f['ayuda'] ?? '');
            if (mb_strlen($ayuda) > 255 || TramiteService::contieneDireccionWeb($ayuda)) {
                throw new FlujoException('La ayuda de «'.$etiqueta.'» admite hasta 255 caracteres y no puede contener direcciones web.');
            }
            $out[] = ['id' => ! empty($f['id']) ? (int) $f['id'] : null, 'clave' => $clave, 'etiqueta' => $etiqueta, 'tipo' => $tipo, 'ayuda' => $ayuda ?: null,
                'obligatorio' => ! in_array($tipo, ['calculo', 'titulo'], true) && ! empty($f['obligatorio']), 'config' => $this->config($tipo, $f, $etiqueta)];
        }
        if (count($out) > self::MAX_CAMPOS) {
            throw new FlujoException('Máximo '.self::MAX_CAMPOS.' campos por trámite.');
        }
        $this->validarFormulas($out);

        return $out;
    }

    /** @param array<string,bool> $usadas */
    private function clave(string $pedida, string $etiqueta, array $usadas): string
    {
        $c = strtolower(trim($pedida));
        if ($c === '') {
            $c = Str::slug($etiqueta, '_');
            $c = preg_replace('/[^a-z0-9_]/', '', $c) ?? '';
            $c = ltrim($c, '0123456789_');
            $c = mb_substr($c ?: 'campo', 0, 26);
            $base = $c;
            for ($i = 2; isset($usadas[$c]) || in_array($c, self::RESERVADAS, true); $i++) {
                $c = $base.'_'.$i;
            }

            return $c;
        }
        if (! preg_match('/^[a-z][a-z0-9_]{0,29}$/', $c)) {
            throw new FlujoException('La clave «'.$pedida.'» no es válida: use letras minúsculas, números y guion bajo, empezando con una letra (máx. 30).');
        }
        if (in_array($c, self::RESERVADAS, true)) {
            throw new FlujoException('La clave «'.$c.'» está reservada para las fórmulas.');
        }
        if (isset($usadas[$c])) {
            throw new FlujoException('La clave «'.$c.'» está repetida.');
        }

        return $c;
    }

    /** @param array<string,mixed> $f @return array<string,mixed> */
    private function config(string $tipo, array $f, string $etiqueta): array
    {
        $num = function (string $k, ?float $min = null, ?float $max = null) use ($f, $etiqueta): ?float {
            $v = trim((string) ($f[$k] ?? ''));
            if ($v === '') {
                return null;
            }
            if (! preg_match('/^-?\d+(\.\d+)?$/', $v) || abs((float) $v) > 1e12) {
                throw new FlujoException('«'.$etiqueta.'»: el valor de «'.$k.'» no es un número válido.');
            }
            $x = (float) $v;
            if (($min !== null && $x < $min) || ($max !== null && $x > $max)) {
                throw new FlujoException('«'.$etiqueta.'»: «'.$k.'» debe estar entre '.($min ?? '-∞').' y '.($max ?? '∞').'.');
            }

            return $x;
        };
        $texto = function (string $k, int $max) use ($f, $etiqueta): ?string {
            $v = TramiteService::limpiarTexto($f[$k] ?? '');
            if ($v === '') {
                return null;
            }
            if (mb_strlen($v) > $max || TramiteService::contieneDireccionWeb($v)) {
                throw new FlujoException('«'.$etiqueta.'»: «'.$k.'» admite hasta '.$max.' caracteres y no puede contener direcciones web.');
            }

            return $v;
        };
        $c = [];
        switch ($tipo) {
            case 'texto':
                $c['maxlen'] = (int) ($num('maxlen', 1, 190) ?? 190);
                break;
            case 'parrafo':
                $c['maxlen'] = (int) ($num('maxlen', 1, 2000) ?? 2000);
                break;
            case 'numero':
                $c['min'] = $num('min');
                $c['max'] = $num('max');
                if ($c['min'] !== null && $c['max'] !== null && $c['min'] > $c['max']) {
                    throw new FlujoException('«'.$etiqueta.'»: el mínimo no puede ser mayor que el máximo.');
                }
                $c['decimales'] = (int) ($num('decimales', 0, 6) ?? 0);
                $c['prefijo'] = $texto('prefijo', 12);
                $c['sufijo'] = $texto('sufijo', 12);
                break;
            case 'fecha':
                foreach (['fecha_min', 'fecha_max'] as $k) {
                    $v = strtolower(trim((string) ($f[$k] ?? '')));
                    if ($v !== '' && $v !== 'hoy' && Formula::diaDe($v) === null) {
                        throw new FlujoException('«'.$etiqueta.'»: «'.$k.'» debe ser «hoy» o una fecha válida (AAAA-MM-DD).');
                    }
                    $c[$k] = $v === '' ? null : $v;
                }
                if ($c['fecha_min'] && $c['fecha_max'] && $c['fecha_min'] !== 'hoy' && $c['fecha_max'] !== 'hoy' && $c['fecha_min'] > $c['fecha_max']) {
                    throw new FlujoException('«'.$etiqueta.'»: la fecha mínima no puede ser posterior a la máxima.');
                }
                break;
            case 'lista':
            case 'multiple':
                $listaId = (int) ($f['lista_id'] ?? 0);
                if ($listaId > 0) {
                    $l = ListaOpciones::query()->withCount('items')->find($listaId);
                    if (! $l || $l->items_count < 1) {
                        throw new FlujoException('«'.$etiqueta.'»: el listado elegido no existe o no tiene opciones.');
                    }
                    $c['lista_id'] = $l->id;
                } else {
                    $c['opciones'] = self::parsearOpciones((string) ($f['opciones'] ?? ''), $etiqueta);
                }
                if ($tipo === 'multiple') {
                    $c['max_elegidas'] = (int) ($num('max_elegidas', 1, 50) ?? 0) ?: null;
                }
                break;
            case 'calculo':
                $formula = trim((string) ($f['formula'] ?? ''));
                Formula::analizar($formula); // lanza FlujoException con el detalle
                $c['formula'] = $formula;
                $c['decimales'] = (int) ($num('decimales', 0, 6) ?? 0);
                $c['prefijo'] = $texto('prefijo', 12);
                $c['sufijo'] = $texto('sufijo', 12);
                break;
        }

        return array_filter($c, fn ($v) => $v !== null);
    }

    /**
     * Opciones desde un texto: una por línea, «valor;etiqueta» o solo «etiqueta» (el valor se deduce de la etiqueta).
     *
     * @return list<array{valor:string,etiqueta:string}>
     *
     * @throws FlujoException
     */
    public static function parsearOpciones(string $texto, string $contexto = 'el listado'): array
    {
        $out = [];
        $vistos = [];
        foreach (preg_split('/\R/u', trim($texto)) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            if (str_contains($linea, ';')) {
                [$valor, $etq] = array_map('trim', explode(';', $linea, 2));
            } else {
                $etq = $linea;
                $valor = Str::slug($linea, '_');
            }
            $etq = TramiteService::limpiarTexto($etq);
            if ($etq === '' || mb_strlen($etq) > 190 || TramiteService::contieneDireccionWeb($etq)) {
                throw new FlujoException('«'.$contexto.'»: una opción tiene una etiqueta inválida (hasta 190 caracteres y sin direcciones web): '.mb_substr($linea, 0, 40));
            }
            if (! preg_match('/^[A-Za-z0-9._\-]{1,60}$/', $valor)) {
                throw new FlujoException('«'.$contexto.'»: el valor «'.mb_substr($valor, 0, 30).'» no es válido (letras, números, punto, guion y guion bajo; hasta 60).');
            }
            if (isset($vistos[strtolower($valor)])) {
                throw new FlujoException('«'.$contexto.'»: el valor «'.$valor.'» está repetido.');
            }
            $vistos[strtolower($valor)] = true;
            $out[] = ['valor' => $valor, 'etiqueta' => $etq];
        }
        if (count($out) < 2) {
            throw new FlujoException('«'.$contexto.'»: indique al menos 2 opciones.');
        }
        if (count($out) > self::MAX_OPCIONES) {
            throw new FlujoException('«'.$contexto.'»: máximo '.self::MAX_OPCIONES.' opciones.');
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $campos */
    private function validarFormulas(array $campos): void
    {
        $por = [];
        foreach ($campos as $c) {
            $por[$c['clave']] = $c;
        }
        $deps = [];
        foreach ($campos as $c) {
            if ($c['tipo'] !== 'calculo') {
                continue;
            }
            $vars = Formula::variables(Formula::analizar($c['config']['formula']));
            foreach ($vars as $v) {
                if ($v === $c['clave']) {
                    throw new FlujoException('El cálculo «'.$c['etiqueta'].'» no puede usarse a sí mismo.');
                }
                if (! isset($por[$v])) {
                    throw new FlujoException('El cálculo «'.$c['etiqueta'].'» usa «'.$v.'», que no es un campo de este formulario. Campos disponibles: '.(implode(', ', array_keys(array_filter($por, fn ($x) => in_array($x['tipo'], self::NUMERICOS, true)))) ?: 'ninguno').'.');
                }
                $t = $por[$v]['tipo'];
                if (! in_array($t, self::NUMERICOS, true)) {
                    throw new FlujoException('El cálculo «'.$c['etiqueta'].'» usa «'.$v.'», que es de tipo «'.TramiteCampo::TIPOS[$t].'»: solo se pueden usar números, fechas, sí/no, listas con valores numéricos y otros cálculos.');
                }
                if ($t === 'lista') {
                    foreach ($this->opcionesDe($por[$v]['config']) as $o) {
                        if (! is_numeric($o['valor'])) {
                            throw new FlujoException('El cálculo «'.$c['etiqueta'].'» usa la lista «'.$por[$v]['etiqueta'].'», pero la opción «'.$o['etiqueta'].'» tiene el valor «'.$o['valor'].'», que no es numérico (escriba «valor;etiqueta», por ejemplo «0.5;Media tarifa»).');
                        }
                    }
                }
            }
            $deps[$c['clave']] = array_values(array_filter($vars, fn ($v) => ($por[$v]['tipo'] ?? '') === 'calculo'));
        }
        // sin ciclos entre cálculos
        $estado = [];
        $visita = function (string $k) use (&$visita, &$estado, $deps, $por) {
            if (($estado[$k] ?? 0) === 1) {
                throw new FlujoException('Los cálculos se usan entre sí en círculo (incluye «'.$por[$k]['etiqueta'].'»).');
            }
            if (($estado[$k] ?? 0) === 2) {
                return;
            }
            $estado[$k] = 1;
            foreach ($deps[$k] ?? [] as $d) {
                $visita($d);
            }
            $estado[$k] = 2;
        };
        foreach (array_keys($deps) as $k) {
            $visita($k);
        }
    }

    /**
     * Opciones de la configuración de un campo: las propias o las del listado reutilizable.
     *
     * @param  array<string,mixed>  $config
     * @return list<array{valor:string,etiqueta:string}>
     */
    public function opcionesDe(array $config): array
    {
        if (! empty($config['lista_id'])) {
            return ListaOpciones::query()->find((int) $config['lista_id'])?->items()->get()->map(fn ($i) => ['valor' => $i->valor, 'etiqueta' => $i->etiqueta])->all() ?? [];
        }

        return array_values((array) ($config['opciones'] ?? []));
    }

    /**
     * Guarda los campos del trámite (los que ya no están se desactivan; las solicitudes antiguas conservan sus respuestas).
     *
     * @param  list<array<string,mixed>>  $campos
     */
    public function guardar(Tramite $t, array $campos): void
    {
        $conservar = [];
        foreach ($campos as $orden => $c) {
            $m = ($c['id'] ? TramiteCampo::query()->where('tramite_id', $t->id)->find($c['id']) : null) ?? TramiteCampo::query()->where('tramite_id', $t->id)->where('clave', $c['clave'])->first();
            $datos = ['clave' => $c['clave'], 'etiqueta' => $c['etiqueta'], 'tipo' => $c['tipo'], 'ayuda' => $c['ayuda'], 'obligatorio' => $c['obligatorio'], 'orden' => $orden, 'activo' => true, 'config' => $c['config']];
            if ($m) {
                $m->forceFill($datos)->save();
            } else {
                $m = TramiteCampo::query()->create(['tramite_id' => $t->id] + $datos);
            }
            $conservar[] = $m->id;
        }
        TramiteCampo::query()->where('tramite_id', $t->id)->whereNotIn('id', $conservar)->update(['activo' => false]);
        $t->unsetRelation('campos');
    }

    /** @return array<int,string> motivos por los que el formulario impide publicar el trámite */
    public function problemas(Tramite $t): array
    {
        $p = [];
        $campos = $t->campos()->get();
        try {
            $this->validarFormulas($campos->map(fn ($c) => ['clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => $c->tipo, 'config' => (array) $c->config])->all());
        } catch (FlujoException $e) {
            $p[] = 'Formulario: '.$e->getMessage();
        }
        foreach ($campos as $c) {
            if (in_array($c->tipo, ['lista', 'multiple'], true) && count($this->opcionesDe((array) $c->config)) < 1) {
                $p[] = 'Formulario: el campo «'.$c->etiqueta.'» no tiene opciones.';
            }
        }

        return $p;
    }

    /** @return list<array<string,mixed>> definición lista para copiar a otro trámite (en el mismo formato que envía el editor) */
    public function paraDuplicar(Tramite $t): array
    {
        return $t->campos()->get()->map(function ($c) {
            $conf = (array) $c->config;
            $conf['opciones'] = isset($conf['opciones']) ? collect($conf['opciones'])->map(fn ($o) => $o['valor'].';'.$o['etiqueta'])->implode("\n") : '';

            return ['id' => null, 'clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => $c->tipo, 'ayuda' => $c->ayuda, 'obligatorio' => $c->obligatorio] + array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $conf);
        })->all();
    }

    // ---------------------------------------------------------------- respuestas de una solicitud

    /**
     * Valida las respuestas del vecino, calcula los campos calculados y devuelve lo que se guarda en la solicitud.
     *
     * @param  array<string,mixed>  $entrada  lo enviado en `campos[clave]`
     * @return list<array{clave:string,etiqueta:string,tipo:string,valor:mixed,texto:string}>
     *
     * @throws ValidationException con un mensaje por campo (`campos.clave`)
     */
    public function responder(Tramite $t, array $entrada, ?Carbon $hoy = null): array
    {
        $hoy ??= now('America/Santiago');
        $campos = $t->campos()->get();
        $errores = [];
        $vals = [];   // valor tal como se guarda
        $nums = [];   // valor numérico para las fórmulas
        $txt = [];
        foreach ($campos as $c) {
            if ($c->tipo === 'titulo' || $c->tipo === 'calculo') {
                continue;
            }
            $k = $c->clave;
            $raw = $entrada[$k] ?? null;
            try {
                [$vals[$k], $nums[$k], $txt[$k]] = $this->respuesta($c, $raw, $hoy);
            } catch (FlujoException $e) {
                $errores['campos.'.$k] = $e->getMessage();
            }
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
        $asts = [];
        foreach ($campos->where('tipo', 'calculo') as $c) {
            $asts[$c->clave] = Formula::analizar((string) $c->conf('formula'));
        }
        $calc = [];
        $eval = function (string $k) use (&$eval, &$calc, $asts, &$nums, $hoy) {
            if (array_key_exists($k, $calc)) {
                return $calc[$k];
            }
            $calc[$k] = null; // corta cualquier ciclo que haya quedado
            foreach (Formula::variables($asts[$k]) as $v) {
                if (isset($asts[$v])) {
                    $nums[$v] = $eval($v);
                }
            }

            return $calc[$k] = Formula::evaluar($asts[$k], $nums, $hoy);
        };
        $out = [];
        foreach ($campos as $c) {
            if ($c->tipo === 'titulo') {
                continue;
            }
            if ($c->tipo === 'calculo') {
                $v = $eval($c->clave);
                $d = (int) $c->conf('decimales', 0);
                $out[] = ['clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => 'calculo', 'valor' => $v === null ? null : Formula::redondear($v, $d), 'texto' => self::formatear($v, $d, $c->conf('prefijo'), $c->conf('sufijo'))];

                continue;
            }
            $out[] = ['clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => $c->tipo, 'valor' => $vals[$c->clave], 'texto' => $txt[$c->clave]];
        }

        return $out;
    }

    /** @return array{0:mixed,1:?float,2:string} valor guardado, valor para fórmulas y texto */
    private function respuesta(TramiteCampo $c, mixed $raw, Carbon $hoy): array
    {
        $e = $c->etiqueta;
        $vacio = $raw === null || $raw === '' || $raw === [];
        if ($vacio && $c->obligatorio) {
            throw new FlujoException('Complete «'.$e.'».');
        }
        switch ($c->tipo) {
            case 'texto':
            case 'parrafo':
                if (is_array($raw)) {
                    throw new FlujoException('«'.$e.'» no es válido.');
                }
                $v = TramiteService::limpiarTexto((string) $raw, $c->tipo === 'parrafo');
                $max = (int) $c->conf('maxlen', $c->tipo === 'texto' ? 190 : 2000);
                if (mb_strlen($v) > $max) {
                    throw new FlujoException('«'.$e.'» admite hasta '.$max.' caracteres.');
                }
                if ($v === '' && $c->obligatorio) {
                    throw new FlujoException('Complete «'.$e.'».');
                }

                return [$v === '' ? null : $v, null, $v];
            case 'numero':
                if ($vacio) {
                    return [null, 0.0, '—'];
                }
                $s = is_array($raw) ? '' : trim((string) $raw);
                if (! preg_match('/^-?\d+(\.\d+)?$/', $s) || abs((float) $s) > 1e12) {
                    throw new FlujoException('«'.$e.'» debe ser un número (use punto para los decimales).');
                }
                $x = (float) $s;
                $dec = (int) $c->conf('decimales', 0);
                if (preg_match('/\.(\d+)$/', $s, $m) && strlen(rtrim($m[1], '0')) > $dec) {
                    throw new FlujoException('«'.$e.'» admite hasta '.$dec.' decimal(es).');
                }
                if (($c->conf('min') !== null && $x < $c->conf('min')) || ($c->conf('max') !== null && $x > $c->conf('max'))) {
                    throw new FlujoException('«'.$e.'» debe estar entre '.self::formatear((float) ($c->conf('min') ?? -1e12), $dec).' y '.self::formatear((float) ($c->conf('max') ?? 1e12), $dec).'.');
                }

                return [$x, $x, self::formatear($x, $dec, $c->conf('prefijo'), $c->conf('sufijo'))];
            case 'fecha':
                if ($vacio) {
                    return [null, null, '—'];
                }
                $s = is_array($raw) ? '' : trim((string) $raw);
                $d = Formula::diaDe($s);
                if ($d === null) {
                    throw new FlujoException('«'.$e.'» debe ser una fecha válida.');
                }
                $hoyIso = $hoy->format('Y-m-d');
                foreach (['fecha_min' => '<', 'fecha_max' => '>'] as $k => $op) {
                    $lim = $c->conf($k);
                    if ($lim) {
                        $l = Formula::diaDe($lim === 'hoy' ? $hoyIso : $lim);
                        if (($op === '<' && $d < $l) || ($op === '>' && $d > $l)) {
                            throw new FlujoException('«'.$e.'» debe ser '.($op === '<' ? 'desde el ' : 'hasta el ').Carbon::parse($lim === 'hoy' ? $hoyIso : $lim)->format('d-m-Y').($lim === 'hoy' ? ' (hoy)' : '').'.');
                        }
                    }
                }

                return [$s, $d, Carbon::parse($s)->format('d-m-Y')];
            case 'si_no':
                if ($vacio) {
                    return [null, 0.0, '—'];
                }
                if (! in_array($raw, ['0', '1', 0, 1], true)) {
                    throw new FlujoException('Elija Sí o No en «'.$e.'».');
                }

                return [(int) $raw, (float) (int) $raw, (int) $raw === 1 ? 'Sí' : 'No'];
            case 'lista':
                if ($vacio) {
                    return [null, null, '—'];
                }
                $ops = $this->opcionesDe((array) $c->config);
                $o = collect($ops)->firstWhere('valor', is_array($raw) ? null : (string) $raw);
                if (! $o) {
                    throw new FlujoException('Elija una opción válida en «'.$e.'».');
                }

                return [$o['valor'], is_numeric($o['valor']) ? (float) $o['valor'] : null, $o['etiqueta']];
            default: // multiple
                if ($vacio) {
                    return [[], null, '—'];
                }
                $ops = collect($this->opcionesDe((array) $c->config));
                $sel = array_values(array_unique(array_map('strval', (array) $raw)));
                $maxE = (int) $c->conf('max_elegidas', 0);
                if ($maxE && count($sel) > $maxE) {
                    throw new FlujoException('En «'.$e.'» puede elegir hasta '.$maxE.' opciones.');
                }
                $etqs = [];
                foreach ($sel as $s) {
                    $o = $ops->firstWhere('valor', $s);
                    if (! $o) {
                        throw new FlujoException('Elija opciones válidas en «'.$e.'».');
                    }
                    $etqs[] = $o['etiqueta'];
                }

                return [$sel, null, implode(', ', $etqs)];
        }
    }

    /** Número con formato chileno (1.234,56) y prefijo o sufijo; «—» si no se pudo calcular. */
    public static function formatear(?float $x, int $dec = 0, ?string $pre = null, ?string $suf = null): string
    {
        if ($x === null) {
            return '—';
        }

        return ($pre ? $pre.' ' : '').number_format(Formula::redondear($x, $dec), $dec, ',', '.').($suf ? ' '.$suf : '');
    }

    public function auditar(Tramite $t, int $campos): void
    {
        $this->audit->registrar('tramite.campos', 'tramite', $t->id, ['campos' => $campos]);
    }

    /** @return Collection<int,TramiteCampo> */
    public function activos(Tramite $t): Collection
    {
        return $t->campos()->get();
    }
}
