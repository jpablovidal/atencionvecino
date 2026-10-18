<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Subidas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class ProgresoPasosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_toda_vista_con_layout_trae_la_barra_de_carga_y_sus_scripts_sin_codigo_en_linea(): void
    {
        foreach (['/', '/tramites', '/ingreso'] as $ruta) {
            $h = $this->get($ruta)->assertOk()->getContent();
            $this->assertStringContainsString('id="sgd-carga"', $h, $ruta);
            $this->assertStringContainsString('role="progressbar"', $h);
            $this->assertStringContainsString('assets/js/carga-init.js', $h);
            $this->assertStringContainsString('assets/js/progreso.js', $h);
            $this->assertStringContainsString('name="sgd-max-subida"', $h);
            $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*\bsrc=)[^>]*>#i', $h, 'sin scripts en línea (CSP)');
        }
    }

    public function test_los_formularios_largos_se_distribuyen_por_pasos(): void
    {
        $h = $this->comoCiudadano()->get('/ciudadano/nueva/1')->assertOk()->getContent();
        $this->assertStringContainsString('data-pasos', $h);
        $this->assertSame(3, preg_match_all('/data-paso="/', $h));
        foreach (['Sus datos y solicitud', 'Documentos', 'Notificación'] as $t) {
            $this->assertStringContainsString('data-paso="'.$t.'"', $h);
        }
        auth()->forgetGuards();
        $this->flushSession();
        $h = $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app/archivo/nueva');
        if ($h->status() === 200) { // quien puede registrar microformas
            $this->assertSame(3, preg_match_all('/data-paso="/', $h->getContent()));
        }
    }

    public function test_formulario_de_tramite_por_pasos_solo_si_se_puede_editar(): void
    {
        $dir = Usuario::query()->get()->first(fn ($u) => $u->tienePermiso('tramites.gestionar'));
        $this->assertNotNull($dir);
        $h = $this->comoFuncionario($dir)->get('/app/tramites/nuevo')->assertOk()->getContent();
        $this->assertStringContainsString('data-pasos', $h);
        $this->assertGreaterThanOrEqual(2, preg_match_all('/data-paso="/', $h));
    }

    public function test_el_javascript_de_progreso_cubre_subida_cancelacion_y_pasos(): void
    {
        $js = file_get_contents(public_path('assets/js/progreso.js'));
        foreach (['upload.onprogress', 'XMLHttpRequest', 'x.abort()', 'aria-current', 'data-pasos', 'reportValidity', 'sgd-max-subida', 'prefers-reduced-motion'] as $k) {
            $this->assertStringContainsString($k, $js);
        }
        $this->assertStringNotContainsString('eval(', $js);
        $this->assertStringNotContainsString('innerHTML', $js, 'los textos del usuario se insertan solo con textContent (sin XSS)');
    }

    public function test_limite_de_subida_del_servidor(): void
    {
        foreach (['8M' => 8 * 1048576, '2G' => 2 * 1073741824, '512K' => 524288, '1000' => 1000, '0' => 0, '' => 0] as $v => $esperado) {
            $this->assertSame($esperado, Subidas::aBytes((string) $v), "'$v'");
        }
        $this->assertGreaterThanOrEqual(0, Subidas::maximoBytes());
    }
}
