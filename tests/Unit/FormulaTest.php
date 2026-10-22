<?php

namespace Tests\Unit;

use App\Exceptions\FlujoException;
use App\Support\Formula;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** Motor de fórmulas de los campos calculados: vectores compartidos con el navegador (tests/fixtures/formulas.json). */
class FormulaTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function vectores(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../fixtures/formulas.json'), true);
    }

    /** @return array{0:?float,1:bool} resultado y si hubo error de análisis */
    private function correr(array $v): array
    {
        try {
            $ast = Formula::analizar($v['f']);
        } catch (FlujoException) {
            return [null, true];
        }
        $vars = [];
        foreach ($v['v'] ?? [] as $k => $x) {
            $vars[$k] = is_string($x) ? Formula::diaDe($x) : $x;
        }

        return [Formula::evaluar($ast, $vars, Carbon::parse($v['hoy'] ?? '2026-10-22')), false];
    }

    public function test_vectores_de_referencia(): void
    {
        foreach ($this->vectores() as $v) {
            [$r, $err] = $this->correr($v);
            if (! empty($v['e'])) {
                $this->assertTrue($err, 'Debía fallar: '.mb_substr($v['f'], 0, 40));

                continue;
            }
            $this->assertFalse($err, $v['f']);
            $v['r'] === null ? $this->assertNull($r, $v['f']) : $this->assertEqualsWithDelta($v['r'], $r, 1e-9, $v['f']);
        }
    }

    public function test_el_navegador_da_los_mismos_resultados_que_el_servidor(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('Node.js no está disponible.');
        }
        $salida = json_decode((string) shell_exec(escapeshellarg($node).' '.escapeshellarg(__DIR__.'/../fixtures/formula_paridad.cjs').' 2>&1'), true);
        $this->assertIsArray($salida, 'El motor del navegador no produjo resultados');
        foreach ($this->vectores() as $i => $v) {
            [$r, $err] = $this->correr($v);
            $js = $salida[$i];
            $this->assertSame($err, ! empty($js['e']), 'Error de análisis distinto en: '.mb_substr($v['f'], 0, 40));
            if (! $err) {
                $r === null ? $this->assertNull($js['r'], $v['f']) : $this->assertEqualsWithDelta($r, $js['r'], 1e-9, $v['f']);
            }
        }
    }

    public function test_variables_y_diadefecha(): void
    {
        $ast = Formula::analizar('monto*iva/100 + si({edad}>=65;0;1) + hoy');
        $this->assertEqualsCanonicalizing(['monto', 'iva', 'edad'], Formula::variables($ast));
        $this->assertSame(0.0, Formula::diaDe('1970-01-01'));
        $this->assertNull(Formula::diaDe('2026-02-30'));
        $this->assertNull(Formula::diaDe('hoy'));
    }

    public function test_no_ejecuta_codigo(): void
    {
        foreach (['system("ls")', '`ls`', '$a', 'phpinfo()', '{a}; drop', 'a.b', '1; 2', "1\x00"] as $mal) {
            $this->expectedFalla($mal);
        }
        $this->assertSame(1.0, Formula::evaluar(Formula::analizar('1'), []));
    }

    private function expectedFalla(string $f): void
    {
        try {
            Formula::analizar($f);
            $this->fail('Debía rechazar: '.$f);
        } catch (FlujoException) {
            $this->assertTrue(true);
        }
    }

    public function test_limites(): void
    {
        $this->expectedFalla(str_repeat('1+', 250).'1');
        $this->expectedFalla(str_repeat('(', 40).'1'.str_repeat(')', 40));
        $this->expectedFalla(str_repeat('a', 401));
    }
}
