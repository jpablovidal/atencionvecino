<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\Admin\AsignacionMasivaService;
use App\Services\DelegacionService;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Perfil «Administrador Municipal»: como el Alcalde pero de menor rango. */
class AdministradorMunicipalTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_tiene_el_panel_de_alcaldia_y_opera_expedientes_pero_con_menos_permisos_que_el_alcalde(): void
    {
        $am = $this->usuario('adm_municipal');
        $al = $this->usuario('alcalde');
        $this->assertSame('adm_municipal', $am->rolBase());
        $this->assertTrue($am->operaExpedientes());
        foreach (['alcaldia.panel', 'expedientes.operar', 'expedientes.crear', 'documentos.crear', 'comunicaciones.ver', 'seguimiento.consultar'] as $p) {
            $this->assertTrue($am->tienePermiso($p), $p);
        }
        foreach (['restringidos.gestionar', 'expedientes.exportar', 'decretopago.ver', 'pisee.consultar', 'delegaciones.crear', 'archivo.disposicion', 'roles.gestionar'] as $p) {
            $this->assertFalse($am->tienePermiso($p), $p);
        }
        $this->assertTrue($al->tienePermiso('restringidos.gestionar'));
        $this->assertTrue(count($am->permisosEfectivos()) < count($al->permisosEfectivos()));
    }

    public function test_no_firma_como_alcalde_sin_una_delegacion(): void
    {
        $am = $this->usuario('adm_municipal');
        $tipo = Documento::query()->with('tipo')->first()?->tipo ?? TipoDocumento::query()->firstOrFail();
        $this->assertFalse(app(DelegacionService::class)->puedeFirmarComoAlcalde($am, $tipo));
        $this->assertTrue(app(DelegacionService::class)->puedeFirmarComoAlcalde($this->usuario('alcalde'), $tipo));
    }

    public function test_ve_el_panel_de_alcaldia_y_la_bandeja_separada(): void
    {
        $am = $this->usuario('adm_municipal');
        $this->comoFuncionario($am)->get('/app/alcaldia')->assertOk();
        $this->comoFuncionario($am)->get('/app')->assertOk()->assertSee('Por firmar')->assertSee('Por visar');
    }

    public function test_el_rol_existe_como_rol_del_sistema_y_se_puede_asignar_en_lote(): void
    {
        $this->assertDatabaseHas('roles', ['clave' => 'adm_municipal', 'sistema' => 1, 'nombre' => 'Administrador Municipal']);
        $this->assertContains('adm_municipal', Usuario::ROLES);
        $this->assertNotEmpty(Permisos::deBase('adm_municipal'));
        $f = $this->usuario('funcionario');
        $res = app(AsignacionMasivaService::class)->aplicar('rol', [$f->id], ['rol' => 'adm_municipal'], $this->usuario('admin'));
        $this->assertSame(1, $res['aplicados']);
        $this->assertSame('adm_municipal', $f->fresh()->rol);
    }
}
