<?php

namespace Tests\Unit;

use App\Support\Run;
use PHPUnit\Framework\TestCase;

class RunTest extends TestCase
{
    public function test_normaliza_y_valida_modulo_11(): void
    {
        $this->assertSame('12345678-5', Run::normalizar('12.345.678-5'));
        $this->assertSame('12345678-5', Run::normalizar('123456785'));
        $this->assertSame('6-K', Run::normalizar('6-k'));
        $this->assertNull(Run::normalizar('12.345.678-9'));
        $this->assertNull(Run::normalizar('abc'));
        $this->assertNull(Run::normalizar(''));
        $this->assertSame('12345678', Run::sinDv('12345678-5'));
    }
}
