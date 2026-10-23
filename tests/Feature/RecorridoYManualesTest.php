<?php

namespace Tests\Feature;

use App\Support\ManualRenderer;
use App\Support\Recorrido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Manuales según el perfil (el público y los vecinos solo ven el del ciudadano) y recorrido guiado sobre la pantalla real. */
class RecorridoYManualesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_los_enlaces_del_recorrido_apuntan_a_encabezados_que_existen_en_el_manual(): void
    {
        foreach (['ciudadano', 'funcionario', 'administrador', 'publico'] as $perfil) {
            $md = (string) file_get_contents(resource_path('manuales/'.($perfil === 'publico' ? 'ciudadano' : $perfil).'.md')); // el público solo accede al manual del ciudadano
            preg_match_all('/^#{1,6}\s+(.*)$/m', $md, $m);
            $ids = array_map(fn ($t) => Str::slug(trim($t)), $m[1]);
            foreach (Recorrido::pasos($perfil) as $p) {
                if (isset($p['manual'])) {
                    $this->assertContains(Str::slug($p['manual']), $ids, "«{$p['manual']}» no es un encabezado de $perfil.md");
                }
            }
        }
    }

    public function test_el_publico_y_los_vecinos_solo_ven_el_manual_del_ciudadano(): void
    {
        $this->sembrar();
        $this->get('/ayuda')->assertRedirect(route('ayuda.manual', 'ciudadano'));
        $this->get('/ayuda/ciudadano')->assertOk()->assertSee('Manual: Ciudadano', false);
        foreach (['funcionario', 'administrador', 'publico', 'tesorero', 'alcalde'] as $otro) {
            $this->get("/ayuda/$otro")->assertRedirect(route('ayuda.manual', 'ciudadano'));
        }
        $this->comoCiudadano()->get('/ayuda')->assertRedirect(route('ayuda.manual', 'ciudadano'));
        $this->comoCiudadano()->get('/ayuda/administrador')->assertRedirect(route('ayuda.manual', 'ciudadano'));
        $this->comoCiudadano()->get('/ayuda/ciudadano')->assertOk()->assertDontSee('Todos los manuales');
        // la portada, el pie y el menú del vecino llevan directo al manual del ciudadano; no hay un listado de manuales
        $this->get('/')->assertOk()->assertSee('Manual del ciudadano')->assertSee(route('ayuda.manual', 'ciudadano'), false)->assertDontSee('Ayuda y manuales')->assertDontSee('Guías paso a paso y declaración');
        $this->comoCiudadano()->get('/ciudadano')->assertOk()->assertSee('Manual del ciudadano')->assertDontSee('Manuales de usuario')->assertDontSee('Ayuda y manuales');
    }

    public function test_cada_funcionario_ve_los_manuales_de_su_perfil(): void
    {
        $this->sembrar();
        $f = $this->comoFuncionario($this->usuario('funcionario'));
        $f->get('/ayuda')->assertOk()->assertSee('Funcionario(a)')->assertDontSee('Administrador(a) del sistema');
        $f->get('/ayuda/administrador')->assertRedirect(route('ayuda'));
        $f->get('/ayuda/funcionario')->assertOk()->assertSee('Todos los manuales');
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk()->assertSee('Manuales de usuario')->assertSee('Ayuda y manuales');
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
        $this->assertSame(['ciudadano', 'funcionario', 'publico'], ManualRenderer::perfilesPara($u, false));
        $this->assertSame(['ciudadano'], ManualRenderer::perfilesPara(null, false));
        $this->assertSame(['ciudadano'], ManualRenderer::perfilesPara(null, true));
    }

    public function test_la_pagina_incluye_el_recorrido_del_perfil_sin_ventana_nueva(): void
    {
        $this->sembrar();
        $r = $this->comoCiudadano()->get('/ciudadano')->assertOk();
        $r->assertSee('id="sgd-recorrido"', false)->assertSee('data-perfil="ciudadano"', false)->assertSee('data-recorrido', false)->assertSee('recorrido.js', false)->assertSee('data-auto="1"', false);
        $r->assertDontSee('class="onboarding"', false); // ya no es una pantalla completa que tape la vista
        $pasos = json_decode(html_entity_decode((string) preg_replace('/^.*id="sgd-recorrido"[^>]*data-pasos="([^"]*)".*$/s', '$1', $r->getContent()), ENT_QUOTES), true);
        $this->assertIsArray($pasos);
        $this->assertSame('/ciudadano', $pasos[0]['url']);
        $this->assertSame('.portal-hero', $pasos[0]['sel']);
        $this->assertContains('/tramites', array_column($pasos, 'url'));
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk()->assertSee('data-perfil="funcionario"', false)->assertSee('Su bandeja de trabajo', false);
    }

    public function test_el_publico_ve_el_recorrido_publico_sobre_la_portada(): void
    {
        $this->sembrar();
        $r = $this->get('/')->assertOk()->assertSee('data-perfil="publico"', false)->assertSee('data-auto="0"', false)->assertSee('data-recorrido', false);
        $r->assertSee('Busque su trámite');
    }

    public function test_todos_los_pasos_tienen_enlaces_validos(): void
    {
        $this->sembrar();
        foreach (array_keys(ManualRenderer::PERFILES) as $perfil) {
            foreach (Recorrido::listos($perfil) as $p) {
                $this->assertStringStartsWith(url('/ayuda/'.($perfil === 'publico' ? 'ciudadano' : $perfil)), $p['manual']);
                $this->assertNotNull($p['url'], 'todo paso tiene una pantalla: '.$p['titulo']);
                $this->assertStringStartsWith('/', $p['url']);
            }
            $this->assertNotEmpty(Recorrido::pasos($perfil));
        }
    }

    public function test_favicon_con_el_logo_municipal(): void
    {
        $this->sembrar();
        $h = $this->get('/')->assertOk();
        $h->assertSee('favicon.ico', false)->assertSee('favicon-32.png', false)->assertSee('apple-touch-icon.png', false);
        foreach (['favicon.ico', 'assets/img/favicon-32.png', 'assets/img/favicon-192.png', 'assets/img/apple-touch-icon.png'] as $f) {
            $this->assertGreaterThan(500, filesize(public_path($f)), $f);
        }
    }
}
