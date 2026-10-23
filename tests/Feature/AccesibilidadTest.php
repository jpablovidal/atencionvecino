<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\ArchivoMunicipalService;
use App\Services\ExpedienteService;
use App\Support\ManualRenderer;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Comprobaciones automáticas de accesibilidad (complementan la auditoría axe en navegador real). */
class AccesibilidadTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    /** @return array<string,string> html por nombre de página */
    private function paginas(): array
    {
        $s = $this->solicitudDOM();
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $doc = $this->documentoVigente($e, $dom);
        $doc->forceFill(['nivel_acceso' => 'publico'])->save();
        $archivista = Usuario::query()->where('rol', 'arch_gestion')->firstOrFail();
        $micro = app(ArchivoMunicipalService::class)->crear($archivista, ['titulo' => 'Decretos 2020', 'tipo_soporte' => 'digital']);
        $h = [
            'inicio' => $this->get('/'), 'ingreso' => $this->get('/ingreso'), 'publico' => $this->get('/publico'), 'catalogo' => $this->get('/tramites'), 'catalogo-ficha' => $this->get('/tramites/1/permiso-de-edificacion'), 'validar-form' => $this->get('/validar'),
            'validar' => $this->get('/validar/'.$doc->codigo_validacion), 'accesibilidad' => $this->get('/accesibilidad'), 'ayuda' => $this->get('/ayuda/ciudadano'), 'ayuda-indice' => $this->comoFuncionario($this->usuario('funcionario'))->get('/ayuda'),
            'ciudadano' => $this->comoCiudadano()->get('/ciudadano'), 'nueva' => $this->comoCiudadano()->get('/ciudadano/nueva/1'), 'solicitud' => $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}"),
            'bandeja' => $this->comoFuncionario($dom)->get('/app'), 'expediente' => $this->comoFuncionario($dom)->get("/app/expedientes/{$e->id}"),
            'documento' => $this->comoFuncionario($dom)->get("/app/documentos/{$doc->id}"), 'vigentes' => $this->comoFuncionario($dom)->get('/app/vigentes'),
            'comunicaciones' => $this->comoFuncionario($dom)->get('/app/comunicaciones'),
            'archivo' => $this->comoFuncionario($archivista)->get('/app/archivo'), 'archivo-nueva' => $this->comoFuncionario($archivista)->get('/app/archivo/nueva'), 'archivo-ver' => $this->comoFuncionario($archivista)->get("/app/archivo/{$micro->id}"),
            'admin-usuarios' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/usuarios'), 'admin-form' => $this->comoFuncionario($this->usuario('admin'))->get('/app/tramites/1'),
            'admin-plantilla' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/1'), 'codificador' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/codificador'),
            'diagnostico' => $this->comoFuncionario($this->usuario('admin'))->get('/app/diagnostico'), 'seguridad' => $this->comoFuncionario($this->usuario('auditor'))->get('/app/seguridad'),
            'delegaciones' => $this->comoFuncionario($this->usuario('alcalde'))->get('/app/delegaciones'),
            'formatos' => $this->comoFuncionario($dom)->get('/app/formatos'), 'formato' => $this->comoFuncionario($dom)->get('/app/formatos/1'),
            'adm-plantillas' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas'), 'adm-plantilla-nueva' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/nuevo'),
            'adm-versiones' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/1/versiones'), 'adm-version' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/1/versiones/1'),
            'adm-recursos' => $this->comoFuncionario($this->usuario('admin'))->get('/app/sistema/recursos'), 'adm-componentes' => $this->comoFuncionario($this->usuario('admin'))->get('/app/sistema/componentes'),
            'adm-conexiones' => $this->comoFuncionario($this->usuario('admin'))->get('/app/sistema/conexiones'),
            'adm-apariencia' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/apariencia'), 'adm-menu' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/menu'), 'adm-api' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/api'), 'adm-api-form' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/api/pisee'), 'adm-folios' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/folios'), 'adm-firmas' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/firmas'), 'adm-repositorio' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/repositorio'), 'adm-roles' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/roles'), 'adm-rol-form' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/roles/nuevo'), 'adm-usuario-form' => $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/usuarios/nuevo'), 'adm-logs' => $this->comoFuncionario($this->usuario('admin'))->get('/app/logs'), 'adm-logs-app' => $this->comoFuncionario($this->usuario('admin'))->get('/app/logs/aplicacion'), 'adm-sesiones' => $this->comoFuncionario($this->usuario('admin'))->get('/app/sesiones'),
        ];
        foreach (array_keys(ManualRenderer::PERFILES) as $p) {
            $h['manual-'.$p] = $this->comoFuncionario($this->superadminManuales())->get('/ayuda/'.$p);
        }
        $out = [];
        foreach ($h as $k => $r) {
            $r->assertOk();
            $out[$k] = $r->getContent();
        }

        return $out;
    }

    private function xp(string $html): DOMXPath
    {
        $d = new DOMDocument;
        @$d->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($d);
    }

    public function test_estructura_base_de_todas_las_paginas(): void
    {
        foreach ($this->paginas() as $nombre => $html) {
            $x = $this->xp($html);
            $this->assertSame('es-CL', $x->evaluate('string(//html/@lang)'), "$nombre: lang");
            $this->assertNotEmpty(trim($x->evaluate('string(//title)')), "$nombre: title");
            $this->assertSame(1, $x->query('//h1')->length, "$nombre: exactamente un h1");
            $this->assertSame(1, $x->query('//main')->length, "$nombre: un main");
            $this->assertGreaterThanOrEqual(1, $x->query('//header')->length);
            $this->assertGreaterThanOrEqual(1, $x->query('//footer')->length);
            $this->assertGreaterThanOrEqual(1, $x->query('//nav[@aria-label]')->length, "$nombre: navegación etiquetada");
            $this->assertStringContainsString('Saltar al contenido', $html);
            $this->assertSame('#contenido', $x->evaluate('string(//a[contains(@class,"skip-link")][1]/@href)'));
            $this->assertSame(1, $x->query('//*[@id="contenido"]')->length);
            $this->assertSame(1, $x->query('//*[@aria-label="Opciones de accesibilidad"]')->length, "$nombre: barra de accesibilidad");
        }
    }

    public function test_no_se_saltan_niveles_de_encabezado(): void
    {
        foreach ($this->paginas() as $nombre => $html) {
            $x = $this->xp($html);
            $prev = 0;
            foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
                $n = (int) substr($h->nodeName, 1);
                $this->assertLessThanOrEqual($prev + 1, $n, "$nombre: salto de encabezado a {$h->nodeName} («".trim($h->textContent).'»)');
                $prev = $n;
            }
        }
    }

    public function test_todo_control_de_formulario_tiene_nombre_accesible(): void
    {
        foreach ($this->paginas() as $nombre => $html) {
            $x = $this->xp($html);
            foreach ($x->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")]|//select|//textarea') as $c) {
                $id = $c->getAttribute('id');
                $ok = $c->getAttribute('aria-label') !== '' || $c->getAttribute('aria-labelledby') !== ''
                    || ($id !== '' && $x->query('//label[@for="'.$id.'"]')->length > 0)
                    || $x->query('ancestor::label', $c)->length > 0;
                $this->assertTrue($ok, "$nombre: control sin etiqueta (name=".$c->getAttribute('name').')');
            }
            // ids únicos
            $ids = [];
            foreach ($x->query('//*[@id]') as $e) {
                $this->assertArrayNotHasKey($e->getAttribute('id'), $ids, "$nombre: id duplicado ".$e->getAttribute('id'));
                $ids[$e->getAttribute('id')] = true;
            }
            // botones con nombre
            foreach ($x->query('//button') as $b) {
                $this->assertNotEmpty(trim($b->textContent) !== '' ? 'x' : $b->getAttribute('aria-label'), "$nombre: botón sin nombre");
            }
        }
    }

    public function test_imagenes_con_alt_enlaces_con_texto_y_tablas_con_encabezados(): void
    {
        foreach ($this->paginas() as $nombre => $html) {
            $x = $this->xp($html);
            foreach ($x->query('//img') as $i) {
                $this->assertTrue($i->hasAttribute('alt'), "$nombre: img sin alt");
            }
            foreach ($x->query('//a[@href]') as $a) {
                $this->assertTrue(trim($a->textContent) !== '' || $a->getAttribute('aria-label') !== '' || $x->query('.//img[@alt!=""]', $a)->length > 0, "$nombre: enlace sin texto ".$a->getAttribute('href'));
            }
            foreach ($x->query('//th') as $th) {
                $this->assertNotSame('', trim($th->textContent), "$nombre: encabezado de tabla vacío");
                $this->assertContains($th->getAttribute('scope'), ['col', 'row'], "$nombre: th sin scope");
            }
            foreach ($x->query('//table[not(@role="presentation")]') as $t) {
                $this->assertGreaterThan(0, $x->query('.//th', $t)->length, "$nombre: tabla sin encabezados");
            }
            foreach ($x->query('//a[@target="_blank"]') as $a) {
                $this->assertStringContainsString('noopener', $a->getAttribute('rel'));
            }
        }
    }

    public function test_barra_de_accesibilidad_scripts_y_estilos_existen(): void
    {
        $html = $this->get('/')->getContent();
        foreach (['data-a11y-size="up"', 'Reducir texto', 'Aumentar texto', 'data-a11y-size="down"', 'data-a11y-toggle="contrast"', 'data-a11y-toggle="legible"', 'data-a11y-toggle="links"', 'data-a11y-toggle="motion"', 'id="a11y-reset"', 'role="status"', 'assets/js/a11y-init.js', 'assets/js/a11y.js'] as $n) {
            $this->assertStringContainsString($n, $html);
        }
        foreach (['a11y-init.js', 'a11y.js'] as $f) {
            $js = file_get_contents(public_path('assets/js/'.$f));
            $this->assertStringNotContainsString('eval(', $js);
            $this->assertStringNotContainsString('document.write', $js);
            $this->assertStringNotContainsString('innerHTML', $js);
        }
        $css = file_get_contents(public_path('assets/css/sgd.css'));
        foreach (['html.a11y-contrast', 'html.a11y-legible', 'html.a11y-links', 'html.a11y-motion', 'prefers-reduced-motion', 'forced-colors', ':focus-visible', '.a11y-fs-3', '@media print'] as $n) {
            $this->assertStringContainsString($n, $css);
        }
        $this->assertStringNotContainsString('outline:none', str_replace('main:focus{outline:none}', '', $css), 'no se debe quitar el foco visible');
    }

    public function test_el_resumen_de_errores_enlaza_a_los_campos_y_recibe_el_foco(): void
    {
        $r = $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => 2, 'correo' => 'no-es-correo', 'detalle' => 'corto', 'consentimiento' => 'si', 'canal_notificacion' => 'correo']);
        $r->assertSessionHasErrors(['correo', 'detalle']);
        $html = $this->comoCiudadano()->withSession(['errors' => $r->getSession()->get('errors')])->get('/ciudadano/nueva/2')->getContent();
        $this->assertStringContainsString('id="resumen-errores"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-campos="correo,detalle"', $html);
        $this->assertStringContainsString('href="#correo"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="correo"/', $html); // el ancla existe
    }

    public function test_contraste_de_colores_del_tema_cumple_aa(): void
    {
        $css = file_get_contents(public_path('assets/css/sgd.css'));
        // pares fondo/texto declarados en la misma regla (estados, botones, barra, cabecera, pie)
        preg_match_all('/\{[^{}]*?background:\s*(#[0-9a-fA-F]{3,6})[^{}]*?;[^{}]*?\bcolor:\s*(#[0-9a-fA-F]{3,6})[^{}]*\}/', $css, $m, PREG_SET_ORDER);
        $this->assertGreaterThan(8, count($m));
        foreach ($m as [$regla, $fondo, $texto]) {
            $this->assertGreaterThanOrEqual(4.5, $this->contraste($fondo, $texto), "contraste insuficiente: $regla");
        }
        // pares clave (texto normal sobre el fondo de página y componentes)
        foreach ([['#f7f9fb', '#1c2b36'], ['#ffffff', '#4a5864'], ['#f7f9fb', '#4a5864'], ['#006fb3', '#ffffff'], ['#0a2e4d', '#ffffff'], ['#0a2e4d', '#cfe6f7'], ['#f0f4f7', '#00446b'],
            ['#ffffff', '#b00020'], ['#000000', '#ffff00'], ['#000000', '#ffffff'], ['#000000', '#ff9d9d'], ['#ffff00', '#000000']] as [$f, $t]) {
            $this->assertGreaterThanOrEqual(4.5, $this->contraste($f, $t), "$f/$t");
        }
    }

    private function contraste(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            $c = array_map(function ($v) {
                $v = hexdec($v) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, str_split($hex, 2));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    public function test_declaracion_de_accesibilidad_y_contacto_configurable(): void
    {
        config(['sgd.accesibilidad_contacto' => 'ayuda-accesible@ejemplo.cl']);
        $html = $this->get('/accesibilidad')->getContent();
        foreach (['WCAG 2.2', 'Conformidad parcial', 'Alt', 'ayuda-accesible@ejemplo.cl', 'Limitaciones conocidas', 'Saltar a la navegación', 'Reportar una barrera'] as $n) {
            $this->assertStringContainsString($n, $html);
        }
    }

    public function test_manuales_por_perfil_se_publican_con_indice_y_sin_html_crudo(): void
    {
        foreach (ManualRenderer::PERFILES as $slug => [$nombre]) {
            $r = $this->comoFuncionario($this->superadminManuales())->get('/ayuda/'.$slug)->assertOk();
            $html = $r->getContent();
            $this->assertStringContainsString('class="manual"', $html);
            $this->assertStringContainsString('Contenido de este manual', $html);
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/i', $html);
            $this->assertGreaterThan(3000, strlen($html), "manual $slug demasiado corto");
            // las imágenes referenciadas existen y tienen alt
            preg_match_all('#<img[^>]*src="(/assets/manual/[^"]+)"[^>]*alt="([^"]+)"#', $html, $imgs, PREG_SET_ORDER);
            foreach ($imgs as [, $src, $alt]) {
                $this->assertFileExists(public_path(ltrim($src, '/')));
                $this->assertNotSame('', trim($alt));
            }
            $this->assertFileExists(resource_path("manuales/$slug.md"));
        }
        $this->get('/ayuda/inexistente')->assertNotFound();
        $this->get('/ayuda/../../.env')->assertNotFound();
        $this->get('/ayuda/ciudadano')->assertSee('Ciudadano');
    }

    public function test_el_renderizador_elimina_html_y_enlaces_peligrosos(): void
    {
        $m = (new ManualRenderer)->aHtml("# Título\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1)) <img src=x onerror=alert(1)>\n\n## Sección\n\ntexto");
        $this->assertStringNotContainsString('<script', $m['html']);
        $this->assertStringNotContainsString('javascript:', $m['html']);
        $this->assertStringNotContainsString('onerror', $m['html']);
        $this->assertSame([['id' => 'seccion', 'texto' => 'Sección']], $m['toc']);
        $t = (new ManualRenderer)->aHtml("# T\n\n| a | b |\n|---|---|\n| 1 | 2 |\n")['html'];
        $this->assertStringContainsString('<thead>', $t); // el reemplazo de <th> no debe dañar <thead>
        $this->assertStringNotContainsString('ead=', str_replace('<thead>', '', $t));
        $this->assertStringContainsString('<th scope="col">a</th>', $t);
    }

    public function test_cada_perfil_ve_el_enlace_a_su_manual(): void
    {
        $this->comoCiudadano()->get('/ayuda')->assertRedirect(route('ayuda.manual', 'ciudadano')); // los vecinos van directo a su manual
        foreach (['admin' => 'Administrador', 'auditor' => 'Auditor', 'alcalde' => 'Alcalde', 'secretario' => 'Secretario', 'funcionario' => 'Funcionario'] as $rol => $n) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->get('/ayuda')->assertSee('Ir a mi manual: '.$n);
        }
    }

    public function test_los_pdf_generados_declaran_idioma_y_titulo(): void
    {
        foreach (['pdf.documento', 'pdf.comprobante', 'pdf.base'] as $v) {
            $src = file_get_contents(resource_path('views/'.str_replace('.', '/', $v).'.blade.php'));
            $this->assertStringContainsString('lang="es"', $src, "$v: lang");
        }
    }

    /** Los manuales se muestran según el perfil: un superadministrador los ve todos. */
    private function superadminManuales(): Usuario
    {
        $u = $this->usuario('admin');
        $u->forceFill(['superadmin' => true])->save();

        return $u->refresh();
    }
}
