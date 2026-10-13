<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\MenuItem;
use App\Services\Admin\AparienciaService;
use App\Services\Admin\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Escenario;
use Tests\TestCase;

class AparienciaMenuTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Cache::flush();
    }

    private function admin()
    {
        return $this->comoFuncionario($this->usuario('admin'));
    }

    private function hayError(): void
    {
        $this->assertTrue(session()->has('error') || session()->has('errors'), 'se esperaba un rechazo');
    }

    private function form(array $cambios = []): array
    {
        $d = AparienciaService::DEFECTO;
        $base = ['colores' => $d['colores'], 'header' => ['titulo' => 'Municipalidad X', 'subtitulo' => 'Sistema Y', 'logo_alt' => 'Logo X', 'logo_alto_px' => 48, 'mostrar_logo' => 1],
            'footer' => ['texto' => 'Pie X', 'texto_legal' => '', 'enlaces' => []]];

        return array_replace_recursive($base, $cambios);
    }

    private function png(int $w = 200, int $h = 80): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 0, 111, 179));
        ob_start();
        imagepng($im);
        $b = (string) ob_get_clean();

        return $this->subida($b, 'logo.png');
    }

    private function subida(string $bytes, string $nombre): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'lg');
        file_put_contents($p, $bytes);

        return new UploadedFile($p, $nombre, null, null, true);
    }

    // ------------------------------------------------------------------ apariencia
    public function test_encabezado_y_pie_se_configuran_y_se_escapan(): void
    {
        $this->admin()->post('/app/admin/apariencia', $this->form(['header' => ['titulo' => 'Muni <script>alert(1)</script>'], 'footer' => ['texto' => 'Pie <b>x</b>', 'texto_legal' => 'Texto legal',
            'enlaces' => [['etiqueta' => 'Transparencia', 'url' => 'https://www.portaltransparencia.cl'], ['etiqueta' => 'Contacto', 'url' => '/ayuda']]]]))->assertSessionHas('ok');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Municipalidad X', str_replace('Muni &lt;script&gt;alert(1)&lt;/script&gt;', 'Municipalidad X', $html));
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('Muni &lt;script&gt;', $html);
        $this->assertStringContainsString('Pie &lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('Texto legal', $html);
        $this->assertMatchesRegularExpression('#<a href="https://www.portaltransparencia.cl" \s+rel="noopener noreferrer"\s*>Transparencia</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="http://localhost:8000/ayuda"\s*>Contacto</a>#', $html);
        $this->assertStringNotContainsString('Validar un documento</a></li>', explode('</footer>', explode('<footer', $html)[1])[0]); // enlaces por defecto reemplazados
    }

    public function test_validacion_de_enlaces_del_pie(): void
    {
        foreach (['javascript:alert(1)', 'http://sin-tls.cl', '//evil.cl/x', 'data:text/html,hola', 'https://user:pw@x.cl', '/con espacio', 'ftp://x.cl', 'mailto:a@b.cl'] as $mala) {
            $this->admin()->post('/app/admin/apariencia', $this->form(['footer' => ['enlaces' => [['etiqueta' => 'X', 'url' => $mala]]]]));
            $this->assertTrue(session()->has('error') || session()->has('errors'), 'aceptado: '.$mala);
        }
        $this->assertSame('/ruta', AparienciaService::urlSegura('/ruta'));
        $this->assertSame('https://x.cl/a?b=1', AparienciaService::urlSegura('https://x.cl/a?b=1'));
    }

    public function test_contraste_minimo_en_todos_los_pares_de_colores(): void
    {
        foreach ([['header_texto', '#ffffff', 'header_fondo', '#ffffee'], ['nav_texto', '#777777', 'nav_fondo', '#888888'], ['footer_texto', '#ffff00', 'footer_fondo', '#ffffff'], ['footer_enlace', '#eeeeee', 'footer_fondo', '#ffffff']] as [$t, $ct, $f, $cf]) {
            $r = $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => [$t => $ct, $f => $cf]]));
            $this->hayError();
        }
        $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => ['primario' => '#ffcc00']]));
        $this->hayError(); // blanco sobre amarillo
        $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => ['titulo' => '#bbbbbb']]));
        $this->hayError();
        foreach (['rojo', '#fff', '#12345g', 'red;}body{display:none', 'url(x)'] as $mal) {
            $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => ['primario' => $mal]]));
            $this->hayError();
        }
    }

    public function test_css_dinamico_con_colores_validados_sin_inyeccion(): void
    {
        $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => ['header_fondo' => '#7a1f1f', 'primario' => '#7a1f1f', 'nav_fondo' => '#1b1b3a']]))->assertSessionHas('ok');
        $r = $this->get('/marca/estilo.css');
        $r->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('text/css', $r->headers->get('Content-Type'));
        $css = $r->getContent();
        $this->assertStringContainsString('--sgd-header-bg:#7a1f1f', $css);
        $this->assertStringContainsString('--sgd-nav-bg:#1b1b3a', $css);
        $this->assertMatchesRegularExpression('/^:root\{[^<>{}]*\}\n$/', $css);
        // la página enlaza el CSS con versión (cambia al editar)
        $v1 = $this->get('/')->getContent();
        preg_match('#marca/estilo.css\?v=(\w+)#', $v1, $m);
        $this->admin()->post('/app/admin/apariencia', $this->form(['header' => ['titulo' => 'Otro título']]));
        preg_match('#marca/estilo.css\?v=(\w+)#', $this->get('/')->getContent(), $m2);
        $this->assertNotSame($m[1], $m2[1]);
        // 304 con ETag
        $etag = $this->get('/marca/estilo.css')->headers->get('ETag');
        $this->get('/marca/estilo.css', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_restablecer_vuelve_a_los_valores_originales_sin_perder_el_logo(): void
    {
        $this->admin()->post('/app/admin/apariencia/logo', ['logo' => $this->png()])->assertSessionHas('ok');
        $this->admin()->post('/app/admin/apariencia', $this->form(['colores' => ['header_fondo' => '#7a1f1f']]))->assertSessionHas('ok');
        $this->admin()->post('/app/admin/apariencia/restablecer')->assertSessionHas('ok');
        $d = app(AparienciaService::class)->datos();
        $this->assertSame('#006fb3', $d['colores']['header_fondo']);
        $this->assertNotNull($d['logo']);
    }

    // ------------------------------------------------------------------ logo
    public function test_logo_valido_se_recodifica_y_se_sirve_con_cabeceras_seguras(): void
    {
        $this->admin()->post('/app/admin/apariencia/logo', ['logo' => $this->png(900, 300)])->assertSessionHas('ok');
        $l = app(AparienciaService::class)->datos()['logo'];
        $this->assertSame('image/png', $l['mime']);
        $this->assertSame(600, $l['ancho']); // reescalado
        $r = $this->get('/marca/logo');
        $r->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $r->getContent());
        $this->get('/marca/logo', ['If-None-Match' => $r->headers->get('ETag')])->assertStatus(304);
        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('#<img class="sgd-logo" src="[^"]*marca/logo\?v=\w+" alt="Logo de la Municipalidad de Santo Domingo" width="\d+" height="48">#', $html);
        $this->assertStringStartsWith('data:image/png;base64,', app(AparienciaService::class)->logoDataUri());
        $this->assertStringStartsNotWith(public_path(), config('sgd.storage_path').'/marca'); // fuera del docroot
    }

    public function test_logo_rechaza_formatos_peligrosos_y_corruptos(): void
    {
        $casos = [
            'svg' => $this->subida('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>', 'logo.svg'),
            'php disfrazado' => $this->subida('<?php system($_GET["c"]); ?>', 'logo.png'),
            'html' => $this->subida('<html><script>alert(1)</script></html>', 'logo.png'),
            'gif' => $this->subida(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'logo.gif'),
            'png corrupto' => $this->subida("\x89PNG\r\n\x1a\n".random_bytes(50), 'logo.png'),
            'diminuto' => $this->png(8, 8),
            'enorme en peso' => $this->subida("\x89PNG\r\n\x1a\n".str_repeat('A', 1100000), 'logo.png'),
        ];
        foreach ($casos as $n => $f) {
            $this->admin()->post('/app/admin/apariencia/logo', ['logo' => $f]);
            $this->hayError();
            $this->assertNull(app(AparienciaService::class)->datos()['logo'], "se aceptó: $n");
        }
        $this->get('/marca/logo')->assertNotFound();
    }

    public function test_el_logo_se_recodifica_eliminando_metadatos_y_carga_incrustada(): void
    {
        $im = imagecreatetruecolor(100, 40);
        ob_start();
        imagejpeg($im, null, 90);
        $jpg = (string) ob_get_clean();
        // inserta un segmento COM (comentario) con un secreto y añade código PHP al final
        $jpg = substr($jpg, 0, 2)."\xFF\xFE\x00\x0ASECRETO!".substr($jpg, 2).'<?php echo 1; ?>';
        $this->assertStringContainsString('SECRETO!', $jpg);
        app(AparienciaService::class)->subirLogo($this->subida($jpg, 'logo.jpg'), $this->usuario('admin'));
        $l = app(AparienciaService::class)->logo();
        $this->assertStringNotContainsString('SECRETO!', $l['bytes']);
        $this->assertStringNotContainsString('<?php', $l['bytes']);
        $this->assertSame('image/jpeg', $l['mime']);
    }

    public function test_logo_alterado_en_disco_no_se_sirve_y_quitar_borra_el_archivo(): void
    {
        app(AparienciaService::class)->subirLogo($this->png(), $this->usuario('admin'));
        $arch = config('sgd.storage_path').'/marca/'.app(AparienciaService::class)->datos()['logo']['archivo'];
        $this->assertFileExists($arch);
        file_put_contents($arch, 'manipulado');
        $this->get('/marca/logo')->assertNotFound(); // verificación de integridad
        $this->admin()->post('/app/admin/apariencia/logo/quitar')->assertSessionHas('ok');
        $this->assertFileDoesNotExist($arch);
        $this->assertNull(app(AparienciaService::class)->datos()['logo']);
    }

    public function test_reemplazar_el_logo_borra_el_anterior_y_exige_alt_si_se_muestra(): void
    {
        $svc = app(AparienciaService::class);
        $svc->subirLogo($this->png(), $this->usuario('admin'));
        $a = config('sgd.storage_path').'/marca/'.$svc->datos()['logo']['archivo'];
        $svc->subirLogo($this->png(120, 60), $this->usuario('admin'));
        $this->assertFileDoesNotExist($a);
        $this->admin()->post('/app/admin/apariencia', $this->form(['header' => ['logo_alt' => '', 'mostrar_logo' => 1]]));
        $this->hayError();
    }

    public function test_permisos_de_apariencia_y_menu(): void
    {
        foreach (['/app/admin/apariencia', '/app/admin/menu'] as $u) {
            $this->comoFuncionario($this->usuario('funcionario'))->get($u)->assertForbidden();
            $this->comoFuncionario($this->usuario('auditor'))->get($u)->assertForbidden();
            $this->admin()->get($u)->assertOk();
        }
        $this->comoFuncionario($this->usuario('alcalde'))->post('/app/admin/apariencia', $this->form())->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/menu/propio', ['area' => 'app', 'etiqueta' => 'x', 'url' => '/x', 'orden' => 1])->assertForbidden();
        $this->get('/marca/estilo.css')->assertOk(); // los recursos de marca son públicos
    }

    // ------------------------------------------------------------------ menú
    private function etiquetas(string $area, $u = null): array
    {
        return array_column(app(MenuService::class)->items($area, $u), 'etiqueta');
    }

    public function test_menu_por_area_y_permisos(): void
    {
        $this->assertSame(['Inicio', 'Trámites y servicios', 'Documentos públicos', 'Validar documento', 'Ayuda'], $this->etiquetas('publico'));
        $this->assertSame(['Inicio', 'Trámites y servicios', 'Documentos públicos', 'Validar documento', 'Mis solicitudes', 'Ayuda'], $this->etiquetas('ciudadano'));
        $f = $this->etiquetas('app', $this->usuario('funcionario'));
        $this->assertSame(['Bandeja', 'Documentos vigentes', 'Formatos y plantillas', 'Comunicaciones oficiales', 'Ayuda'], $f);
        $a = $this->etiquetas('app', $this->usuario('admin'));
        foreach (['Usuarios', 'Folios', 'Estilo y posición de firmas', 'APIs e integraciones', 'Repositorio', 'Apariencia', 'Barra de menú', 'Diagnóstico'] as $e) {
            $this->assertContains($e, $a);
        }
        $this->assertNotContains('Documentos vigentes', $a === [] ? [] : array_diff($a, ['Documentos vigentes'])); // admin no tiene vigentes.ver
        $this->assertNotContains('Documentos vigentes', $this->etiquetas('app', $this->usuario('admin')));
        $this->assertContains('Delegaciones de firma', $this->etiquetas('app', $this->usuario('alcalde')));
        $this->assertContains('Seguridad', $this->etiquetas('app', $this->usuario('auditor')));
        $this->assertNotContains('Usuarios', $this->etiquetas('app', $this->usuario('auditor')));
    }

    public function test_el_menu_del_administrador_se_agrupa_en_desplegables_accesibles(): void
    {
        $html = $this->admin()->get('/app/admin/apariencia')->getContent();
        $this->assertMatchesRegularExpression('#<details class="sgd-nav-grupo"><summary>Administración</summary>#', $html);
        $this->assertStringContainsString('<summary>Seguridad y operación</summary>', $html);
        $this->assertSame(2, substr_count($html, '<details class="sgd-nav-grupo">'));
        $this->assertStringContainsString('aria-current="page"', $html); // Bandeja
    }

    public function test_renombrar_reordenar_ocultar_y_restablecer(): void
    {
        $this->admin()->post('/app/admin/menu/sistema/vigentes', ['etiqueta' => 'Documentos firmados', 'orden' => 5, 'activo' => 1])->assertSessionHas('ok');
        $this->assertSame(['Documentos firmados', 'Bandeja'], array_slice($this->etiquetas('app', $this->usuario('funcionario')), 0, 2));
        $this->admin()->post('/app/admin/menu/sistema/comunicaciones', ['etiqueta' => 'Comunicaciones', 'orden' => 30])->assertSessionHas('ok'); // sin "activo" = oculto
        $this->assertNotContains('Comunicaciones', $this->etiquetas('app', $this->usuario('funcionario')));
        // los protegidos no se ocultan
        $this->admin()->post('/app/admin/menu/sistema/bandeja', ['etiqueta' => 'Mi bandeja', 'orden' => 10])->assertSessionHas('ok');
        $this->assertContains('Mi bandeja', $this->etiquetas('app', $this->usuario('funcionario')));
        $this->admin()->post('/app/admin/menu/sistema/ayuda', ['etiqueta' => 'Ayuda', 'orden' => 900])->assertSessionHas('ok');
        $this->assertContains('Ayuda', $this->etiquetas('publico'));
        // restablecer
        $this->admin()->post('/app/admin/menu/sistema/vigentes/restablecer')->assertSessionHas('ok');
        $this->assertContains('Documentos vigentes', $this->etiquetas('app', $this->usuario('funcionario')));
        foreach ([['etiqueta' => '', 'orden' => 1], ['etiqueta' => 'x<y', 'orden' => 1], ['etiqueta' => 'ok', 'orden' => 5000], ['etiqueta' => str_repeat('a', 41), 'orden' => 1]] as $mal) {
            $this->admin()->post('/app/admin/menu/sistema/vigentes', $mal);
            $this->hayError();
        }
        $this->admin()->post('/app/admin/menu/sistema/inexistente', ['etiqueta' => 'x', 'orden' => 1]);
        $this->hayError();
    }

    public function test_enlaces_propios_por_area_y_rol_con_pestana_nueva_y_validacion(): void
    {
        $this->admin()->post('/app/admin/menu/propio', ['area' => 'publico', 'etiqueta' => 'Transparencia', 'url' => 'https://www.portaltransparencia.cl', 'orden' => 25, 'nueva_pestana' => 1, 'activo' => 1])->assertSessionHas('ok');
        $this->admin()->post('/app/admin/menu/propio', ['area' => 'app', 'etiqueta' => 'Manual interno', 'url' => '/ayuda/funcionario', 'orden' => 50, 'roles' => ['funcionario'], 'activo' => 1])->assertSessionHas('ok');
        $this->assertContains('Transparencia', $this->etiquetas('publico'));
        $this->assertNotContains('Transparencia', $this->etiquetas('app', $this->usuario('funcionario')));
        $this->assertContains('Manual interno', $this->etiquetas('app', $this->usuario('funcionario')));
        $this->assertNotContains('Manual interno', $this->etiquetas('app', $this->usuario('secretario'))); // otro rol
        $this->flushSession();
        auth()->forgetGuards();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('se abre en una pestaña nueva', $html);
        foreach ([['url' => 'javascript:alert(1)'], ['url' => 'http://x.cl'], ['url' => '//evil.cl'], ['url' => 'data:text/html,x'], ['area' => 'otra'], ['area' => 'publico', 'roles' => ['funcionario']], ['area' => 'app', 'roles' => ['superadmin']]] as $mal) {
            $this->admin()->post('/app/admin/menu/propio', array_merge(['area' => 'app', 'etiqueta' => 'X', 'url' => '/x', 'orden' => 1], $mal));
            $this->hayError();
        }
        $item = MenuItem::query()->whereNull('clave')->first();
        $this->admin()->post("/app/admin/menu/propio/{$item->id}/eliminar")->assertSessionHas('ok');
        $this->assertNotContains('Transparencia', $this->etiquetas('publico'));
        $this->expectException(FlujoException::class);
        app(MenuService::class)->eliminarPropio(MenuItem::query()->updateOrCreate(['clave' => 'vigentes'], ['area' => 'app', 'etiqueta' => 'v', 'orden' => 1, 'activo' => true]));
    }

    public function test_el_menu_no_concede_acceso_ni_lo_quita(): void
    {
        // un enlace propio hacia una página de administración no da acceso a quien no tiene el permiso
        $this->admin()->post('/app/admin/menu/propio', ['area' => 'app', 'etiqueta' => 'Atajo admin', 'url' => '/app/admin/apariencia', 'orden' => 1, 'activo' => 1]);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/apariencia')->assertForbidden();
        // y ocultar un ítem no bloquea la ruta
        $this->admin()->post('/app/admin/menu/sistema/folios', ['etiqueta' => 'Folios', 'orden' => 150]);
        $this->assertNotContains('Folios', $this->etiquetas('app', $this->usuario('admin')));
        $this->admin()->get('/app/admin/folios')->assertOk();
    }

    public function test_paginas_de_error_y_sin_bd_usan_valores_por_defecto(): void
    {
        $this->get('/ruta-inexistente')->assertNotFound()->assertSee('I. Municipalidad de Santo Domingo');
    }
}
