<?php

namespace App\Support;

use App\Exceptions\FlujoException;
use Illuminate\Support\Carbon;

/**
 * Fórmulas de los campos calculados de un trámite. Es un analizador propio (no usa eval): solo admite números, referencias a otros campos,
 * operadores y un conjunto fijo de funciones, con límites de largo, profundidad y tamaño.
 *
 * Operadores: `+ - * / % ^` (potencia), comparaciones `< <= > >= == != <>` (devuelven 1 o 0), lógicos `&& || !`.
 * Referencias: `{clave}` o directamente `clave`. Separador de argumentos: `,` o `;`. Decimales con punto (`2.5`).
 * Funciones: si(cond; a; b), min, max, abs, redondear(x; n), piso, techo, raiz, suma, promedio, potencia(a; b), dias(f1; f2), anios(f1; f2).
 * Valores: todo es un número; una fecha vale los días transcurridos desde 1970-01-01 (restar dos fechas da los días entre ellas) y `hoy` es la fecha
 * de hoy (hora de Chile). Un campo numérico vacío vale 0; una fecha vacía hace que el resultado no se pueda calcular (null). Dividir por cero o salirse
 * de rango tampoco se puede calcular. La versión en el navegador (public/assets/js/formulario.js) sigue exactamente las mismas reglas; el servidor
 * recalcula siempre.
 */
class Formula
{
    public const MAX_LARGO = 400;

    public const MAX_NODOS = 150;

    public const MAX_PROFUNDIDAD = 25;

    /** nombre => [mínimo de argumentos, máximo (null = sin límite)] */
    public const FUNCIONES = ['si' => [3, 3], 'min' => [1, null], 'max' => [1, null], 'abs' => [1, 1], 'redondear' => [1, 2], 'piso' => [1, 1], 'techo' => [1, 1], 'raiz' => [1, 1],
        'suma' => [1, null], 'promedio' => [1, null], 'potencia' => [2, 2], 'dias' => [2, 2], 'anios' => [2, 2]];

    /** @var list<array{0:string,1:mixed,2:int}> */
    private array $tokens = [];

    private int $pos = 0;

    private int $nodos = 0;

    private function __construct(private readonly string $src) {}

    /**
     * @return array<int|string,mixed> árbol
     *
     * @throws FlujoException con un mensaje en español que indica dónde está el problema
     */
    public static function analizar(string $fuente): array
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $fuente)) {
            throw new FlujoException('La fórmula contiene caracteres no permitidos.');
        }
        $fuente = trim($fuente);
        if ($fuente === '') {
            throw new FlujoException('La fórmula está vacía.');
        }
        if (mb_strlen($fuente) > self::MAX_LARGO) {
            throw new FlujoException('La fórmula admite hasta '.self::MAX_LARGO.' caracteres.');
        }
        $p = new self($fuente);
        $p->tokens = $p->tokenizar();
        $ast = $p->o(0);
        if ($p->pos < count($p->tokens)) {
            throw $p->error('Sobra «'.$p->tokens[$p->pos][1].'»');
        }

        return $ast;
    }

    /** @return list<string> nombres de los campos que usa la fórmula, sin repetir */
    public static function variables(array $ast): array
    {
        $o = [];
        $rec = function ($n) use (&$rec, &$o) {
            if ($n[0] === 'v' && $n[1] !== 'hoy') {
                $o[$n[1]] = true;
            } elseif ($n[0] === 'u') {
                $rec($n[2]);
            } elseif ($n[0] === 'b') {
                $rec($n[2]);
                $rec($n[3]);
            } elseif ($n[0] === 'f') {
                foreach ($n[2] as $a) {
                    $rec($a);
                }
            }
        };
        $rec($ast);

        return array_keys($o);
    }

    /**
     * @param  array<string,float|int|null>  $vars  valor de cada campo (null = vacío)
     * @return float|null null si no se puede calcular
     */
    public static function evaluar(array $ast, array $vars, ?Carbon $hoy = null): ?float
    {
        $hoy ??= now('America/Santiago');
        $hoyDias = (float) intdiv((int) strtotime($hoy->format('Y-m-d').' 00:00:00 UTC'), 86400);
        $r = self::ev($ast, $vars, $hoyDias);

        return $r === null || ! is_finite($r) ? null : $r;
    }

    /** Valor numérico de una fecha ISO (Y-m-d): días desde 1970-01-01, o null si no es una fecha válida. */
    public static function diaDe(?string $iso): ?float
    {
        if ($iso === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return (float) intdiv((int) strtotime($iso.' 00:00:00 UTC'), 86400);
    }

    public static function redondear(float $x, int $n = 0): float
    {
        return round($x, max(0, min(8, $n)));
    }

    private static function ev(array $n, array $v, float $hoy): ?float
    {
        switch ($n[0]) {
            case 'n':
                return $n[1];
            case 'v':
                if ($n[1] === 'hoy') {
                    return $hoy;
                }

                return array_key_exists($n[1], $v) ? ($v[$n[1]] === null ? null : (float) $v[$n[1]]) : null;
            case 'u':
                $x = self::ev($n[2], $v, $hoy);
                if ($x === null) {
                    return null;
                }

                return match ($n[1]) {
                    '-' => -$x, '+' => $x, default => $x == 0.0 ? 1.0 : 0.0
                };
            case 'b':
                $op = $n[1];
                if ($op === '&&' || $op === '||') {
                    $a = self::ev($n[2], $v, $hoy);
                    if ($a === null) {
                        return null;
                    }
                    if ($op === '&&' && $a == 0.0) {
                        return 0.0;
                    }
                    if ($op === '||' && $a != 0.0) {
                        return 1.0;
                    }
                    $b = self::ev($n[3], $v, $hoy);

                    return $b === null ? null : ($b != 0.0 ? 1.0 : 0.0);
                }
                $a = self::ev($n[2], $v, $hoy);
                $b = self::ev($n[3], $v, $hoy);
                if ($a === null || $b === null) {
                    return null;
                }

                return match ($op) {
                    '+' => $a + $b, '-' => $a - $b, '*' => $a * $b,
                    '/' => $b == 0.0 ? null : $a / $b,
                    '%' => $b == 0.0 ? null : fmod($a, $b),
                    '^' => self::potencia($a, $b),
                    '<' => $a < $b ? 1.0 : 0.0, '<=' => $a <= $b ? 1.0 : 0.0, '>' => $a > $b ? 1.0 : 0.0, '>=' => $a >= $b ? 1.0 : 0.0,
                    '==' => abs($a - $b) < 1e-9 ? 1.0 : 0.0, '!=' => abs($a - $b) < 1e-9 ? 0.0 : 1.0,
                    default => null,
                };
            default: // función
                return self::funcion($n[1], $n[2], $v, $hoy);
        }
    }

    private static function potencia(float $a, float $b): ?float
    {
        if (abs($b) > 100 || ($a == 0.0 && $b < 0)) {
            return null;
        }
        $r = $a ** $b;

        return is_finite($r) ? $r : null;
    }

    private static function funcion(string $f, array $args, array $v, float $hoy): ?float
    {
        if ($f === 'si') {
            $c = self::ev($args[0], $v, $hoy);

            return $c === null ? null : self::ev($c != 0.0 ? $args[1] : $args[2], $v, $hoy);
        }
        $x = [];
        foreach ($args as $a) {
            $r = self::ev($a, $v, $hoy);
            if ($r === null) {
                return null;
            }
            $x[] = $r;
        }

        return match ($f) {
            'min' => min($x), 'max' => max($x), 'abs' => abs($x[0]), 'suma' => array_sum($x), 'promedio' => array_sum($x) / count($x),
            'piso' => floor($x[0]), 'techo' => ceil($x[0]), 'raiz' => $x[0] < 0 ? null : sqrt($x[0]),
            'redondear' => self::redondear($x[0], (int) ($x[1] ?? 0)),
            'potencia' => self::potencia($x[0], $x[1]),
            'dias' => $x[1] - $x[0],
            'anios' => self::anios($x[0], $x[1]),
            default => null,
        };
    }

    /** Años cumplidos entre dos fechas (negativo si la segunda es anterior). */
    private static function anios(float $a, float $b): float
    {
        $signo = 1;
        if ($b < $a) {
            [$a, $b] = [$b, $a];
            $signo = -1;
        }
        [$y1, $m1, $d1] = array_map('intval', explode('-', gmdate('Y-n-j', (int) $a * 86400)));
        [$y2, $m2, $d2] = array_map('intval', explode('-', gmdate('Y-n-j', (int) $b * 86400)));
        $n = $y2 - $y1 - (($m2 < $m1 || ($m2 === $m1 && $d2 < $d1)) ? 1 : 0);

        return (float) ($signo * $n);
    }

    // ---- análisis

    /** @return list<array{0:string,1:mixed,2:int}> */
    private function tokenizar(): array
    {
        $t = [];
        $s = $this->src;
        $n = strlen($s);
        $i = 0;
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
                if (! preg_match('/\G\d*\.?\d+/', $s, $m, 0, $i)) {
                    throw $this->error('Número inválido', $i);
                }
                $t[] = ['n', (float) $m[0], $i];
                $i += strlen($m[0]);

                continue;
            }
            if ($c === '{') {
                if (! preg_match('/\G\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}/', $s, $m, 0, $i)) {
                    throw $this->error('Referencia inválida: use {clave}', $i);
                }
                $t[] = ['v', strtolower($m[1]), $i];
                $i += strlen($m[0]);

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $t[] = ['i', strtolower($m[0]), $i];
                $i += strlen($m[0]);

                continue;
            }
            foreach (['<=', '>=', '==', '!=', '<>', '&&', '||'] as $op) {
                if (substr($s, $i, 2) === $op) {
                    $t[] = ['o', $op === '<>' ? '!=' : $op, $i];
                    $i += 2;

                    continue 2;
                }
            }
            if (str_contains('+-*/%^<>!=(),;', $c)) {
                $c2 = $c === ';' ? ',' : $c; // «;» y «,» separan argumentos
                $t[] = [in_array($c2, ['(', ')', ','], true) ? $c2 : 'o', $c === '=' ? '==' : $c2, $i];
                $i++;

                continue;
            }
            throw $this->error('Carácter no permitido «'.mb_substr(substr($s, $i), 0, 1).'»', $i);
        }

        return $t;
    }

    private function error(string $m, ?int $en = null): FlujoException
    {
        $en ??= $this->tokens[$this->pos][2] ?? mb_strlen($this->src);

        return new FlujoException($m.' (posición '.($en + 1).' de la fórmula).');
    }

    private function sig(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function es(string $tipo, ?string $valor = null): bool
    {
        $t = $this->sig();

        return $t !== null && $t[0] === $tipo && ($valor === null || $t[1] === $valor);
    }

    private function nodo(int $prof): void
    {
        if (++$this->nodos > self::MAX_NODOS) {
            throw $this->error('La fórmula es demasiado larga o compleja');
        }
        if ($prof > self::MAX_PROFUNDIDAD) {
            throw $this->error('La fórmula tiene demasiados niveles de paréntesis');
        }
    }

    private function o(int $p): array
    {
        $a = $this->y($p);
        while ($this->es('o', '||')) {
            $this->pos++;
            $a = ['b', '||', $a, $this->y($p)];
            $this->nodo($p);
        }

        return $a;
    }

    private function y(int $p): array
    {
        $a = $this->cmp($p);
        while ($this->es('o', '&&')) {
            $this->pos++;
            $a = ['b', '&&', $a, $this->cmp($p)];
            $this->nodo($p);
        }

        return $a;
    }

    private function cmp(int $p): array
    {
        $a = $this->sum($p);
        if ($this->sig() && $this->sig()[0] === 'o' && in_array($this->sig()[1], ['<', '<=', '>', '>=', '==', '!='], true)) {
            $op = $this->sig()[1];
            $this->pos++;
            $a = ['b', $op, $a, $this->sum($p)];
            $this->nodo($p);
        }

        return $a;
    }

    private function sum(int $p): array
    {
        $a = $this->mul($p);
        while ($this->sig() && $this->sig()[0] === 'o' && in_array($this->sig()[1], ['+', '-'], true)) {
            $op = $this->sig()[1];
            $this->pos++;
            $a = ['b', $op, $a, $this->mul($p)];
            $this->nodo($p);
        }

        return $a;
    }

    private function mul(int $p): array
    {
        $a = $this->un($p);
        while ($this->sig() && $this->sig()[0] === 'o' && in_array($this->sig()[1], ['*', '/', '%'], true)) {
            $op = $this->sig()[1];
            $this->pos++;
            $a = ['b', $op, $a, $this->un($p)];
            $this->nodo($p);
        }

        return $a;
    }

    private function un(int $p): array
    {
        if ($this->sig() && $this->sig()[0] === 'o' && in_array($this->sig()[1], ['-', '+', '!'], true)) {
            $op = $this->sig()[1];
            $this->pos++;
            $this->nodo($p);

            return ['u', $op, $this->un($p + 1)];
        }

        return $this->pot($p);
    }

    /** La potencia pesa más que el signo (-2^2 = -4) y es asociativa a la derecha (2^3^2 = 2^9). */
    private function pot(int $p): array
    {
        $a = $this->prim($p);
        if ($this->es('o', '^')) {
            $this->pos++;
            $a = ['b', '^', $a, $this->un($p + 1)];
            $this->nodo($p);
        }

        return $a;
    }

    private function prim(int $p): array
    {
        $t = $this->sig();
        if ($t === null) {
            throw $this->error('La fórmula está incompleta');
        }
        $this->nodo($p);
        if ($t[0] === 'n') {
            $this->pos++;

            return ['n', $t[1]];
        }
        if ($t[0] === 'v') {
            $this->pos++;

            return ['v', $t[1]];
        }
        if ($t[0] === '(') {
            $this->pos++;
            $a = $this->o($p + 1);
            if (! $this->es(')')) {
                throw $this->error('Falta cerrar el paréntesis');
            }
            $this->pos++;

            return $a;
        }
        if ($t[0] === 'i') {
            $this->pos++;
            if (! $this->es('(')) {
                return ['v', $t[1]]; // referencia directa a un campo
            }
            if (! isset(self::FUNCIONES[$t[1]])) {
                throw $this->error('La función «'.$t[1].'» no existe. Disponibles: '.implode(', ', array_keys(self::FUNCIONES)), $t[2]);
            }
            $this->pos++;
            $args = [];
            if (! $this->es(')')) {
                do {
                    $args[] = $this->o($p + 1);
                    $sigue = $this->es(',');
                    if ($sigue) {
                        $this->pos++;
                    }
                } while ($sigue);
            }
            if (! $this->es(')')) {
                throw $this->error('Falta cerrar el paréntesis de «'.$t[1].'»');
            }
            $this->pos++;
            [$min, $max] = self::FUNCIONES[$t[1]];
            if (count($args) < $min || ($max !== null && count($args) > $max)) {
                throw $this->error('«'.$t[1].'» recibe '.($max === $min ? $min : ($max === null ? "al menos $min" : "entre $min y $max")).' argumento(s)', $t[2]);
            }

            return ['f', $t[1], $args];
        }
        throw $this->error('No se esperaba «'.$t[1].'»');
    }
}
