<?php

namespace Tests\Feature;

use App\Models\CuentaPrueba;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruebasArchivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_en_pruebas_y_es_idempotente(): void
    {
        config(['sgd.ambiente' => 'produccion']);
        $this->artisan('pruebas:archivo')->assertFailed();
        $this->assertSame(0, Usuario::query()->count());
        config(['sgd.ambiente' => 'pruebas']);
        $this->artisan('pruebas:archivo')->assertSuccessful();
        $this->assertSame(2, Usuario::query()->count());
        $this->assertTrue(Usuario::query()->where('rol', 'arch_gestion')->firstOrFail()->tienePermiso('archivo.gestionar'));
        $this->assertTrue(Usuario::query()->where('rol', 'arch_cert')->firstOrFail()->tienePermiso('archivo.certificar'));
        $this->assertSame(2, CuentaPrueba::query()->count());
        $hash = CuentaPrueba::query()->first()->password;
        $this->artisan('pruebas:archivo')->assertSuccessful(); // segunda vez: no duplica ni cambia contraseñas
        $this->assertSame(2, Usuario::query()->count());
        $this->assertSame($hash, CuentaPrueba::query()->first()->password);
    }
}
