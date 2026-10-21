<?php

namespace Tests\Feature;

use App\Support\ManualRenderer;
use App\Support\Recorrido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Manuales según el perfil y recorrido guiado (componente «onboarding» del framework gob.cl). */
class RecorridoYManualesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_los_enlaces_del_recorrido_apuntan_a_encabezados_que_existen_en_el_manual(): void
    {
        foreach (['ciudadano', 'funcionario', 'administrador', 'publico'] as $perfil) {
            $md = (string) file_get_contents(resource_path("manuales/$perfil.md"));
            preg_match_all('/^#{1,6}\s+(.*)$/m', $md, $m);
            $ids = array_map(fn ($t) => Str::slug(trim($t)), $m[1]);
            foreach (Recorrido::pasos($perfil) as $p) {
                if (isset($p['manual'])) {
                    $this->assertContains(Str::slug($p['manual']), $ids, "«{$p['manual']}» no es un encabezado de $perfil.md");
                }
            }
        }
    }

    public function test_cada_persona_ve_solo_los_manuales_de_su_perfil(): void
    {
        $this->sembrar();
        $this->get('/ayuda')->assertOk()->assertSee('Público general')->assertDontSee('Administrador(a) del sistema')->assertDontSee('Funcionario(a)');
        $this->comoCiudadano()->get('/ayuda')->assertOk()->assertSee('Ciudadano / vecino(a)')->assertDontSee('Funcionario(a)')->assertDontSee('Tesorero');
        $this->comoCiudadano()->get('/ayuda/administrador')->assertRedirect(route('ayuda'));
        $this->comoCiudadano()->get('/ayuda/ciudadano')->assertOk();
        $f = $this->comoFuncionario($this->usuario('funcionario'));
        $f->get('/ayuda')->assertOk()->assertSee('Funcionario(a)')->assertDontSee('Administrador(a) del sistema');
        $f->get('/ayuda/administrador')->assertRedirect(route('ayuda'));
        $a = $this->comoFuncionario($this->usuario('admin'));
        $a->get('/ayuda')->assertOk()->assertSee('Administrador(a) del sistema')->assertDontSee('Tesorero(a) municipal');
        $a->get('/ayuda/publico')->assertOk();
        $s = $this->usuario('admin');
        $s->forceFill(['superadmin' => true])->save();
        $this->comoFuncionario($s)->get('/ayuda')->assertOk()->assertSee('todos los perfiles', false)->assertSee('Tesorero(a) municipal');
    }

    public function test_el_manual_extra_llega_por_permiso(): void
    {
        $this->sembrar();
        $u = $this->usuario('funcionario');
        $this->assertSame(['funcionario', 'publico'], ManualRenderer::perfilesPara($u, false));
    }

    public function test_la_pagina_incluye_el_recorrido_del_perfil_y_las_entradas_del_menu(): void
    {
        $this->sembrar();
        $r = $this->comoCiudadano()->get('/ciudadano')->assertOk();
        $r->assertSee('class="onboarding"', false)->assertSee('data-perfil="ciudadano"', false)->assertSee('Complete su solicitud en 4 pasos', false)->assertSee('data-recorrido', false)->assertSee('Manuales de usuario');
        $r->assertSee('recorrido.js', false)->assertSee('data-auto="1"', false);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk()->assertSee('data-perfil="funcionario"', false)->assertSee('Su bandeja de trabajo', false);
    }

    public function test_el_publico_ve_el_recorrido_publico_sin_abrirse_solo(): void
    {
        $this->sembrar();
        $this->get('/ayuda')->assertOk()->assertSee('data-perfil="publico"', false)->assertSee('data-auto="0"', false)->assertSee('Iniciar el recorrido guiado');
    }

    public function test_todos_los_pasos_tienen_enlaces_validos(): void
    {
        $this->sembrar();
        foreach (array_keys(ManualRenderer::PERFILES) as $perfil) {
            foreach (Recorrido::listos($perfil) as $p) {
                $this->assertStringStartsWith(url('/ayuda/'.$perfil), $p['manual']);
                if ($p['ir']) {
                    $this->assertStringStartsWith(url('/'), $p['ir'][0]);
                }
            }
            $this->assertNotEmpty(Recorrido::pasos($perfil));
        }
    }

    public function test_favicon_con_el_logo_municipal(): void
    {
        $this->sembrar();
        $h = $this->get('/ayuda')->assertOk();
        $h->assertSee('favicon.ico', false)->assertSee('favicon-32.png', false)->assertSee('apple-touch-icon.png', false);
        foreach (['favicon.ico', 'assets/img/favicon-32.png', 'assets/img/favicon-192.png', 'assets/img/apple-touch-icon.png'] as $f) {
            $this->assertGreaterThan(500, filesize(public_path($f)), $f);
        }
    }
}
