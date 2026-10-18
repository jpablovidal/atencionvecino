<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Services\ExpedienteService;
use App\Services\SolicitudService;
use App\Support\HashedDatabaseSessionHandler;
use App\Support\ProductionGuard;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Escenario;
use Tests\TestCase;

class SeguridadHttpTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_cabeceras_de_seguridad_y_csp_estricta(): void
    {
        $r = $this->get('/');
        $csp = $r->headers->get('Content-Security-Policy');
        foreach (["default-src 'self'", "script-src 'self'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'none'", "object-src 'none'"] as $d) {
            $this->assertStringContainsString($d, $csp);
        }
        $this->assertStringNotContainsString("script-src 'self' 'unsafe", $csp);
        $this->assertStringNotContainsString('https://', $csp, 'sin CDN ni orígenes externos');
        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy')->assertHeader('Permissions-Policy');
        $this->assertStringContainsString('max-age=', $r->headers->get('Strict-Transport-Security'));
    }

    public function test_paginas_sin_scripts_en_linea_ni_handlers_ni_cdn(): void
    {
        $s = $this->solicitudDOM();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->usuario('funcionario', 1));
        $paginas = [
            $this->get('/'), $this->get('/publico'), $this->get('/validar'), $this->get('/ingreso'),
            $this->comoCiudadano()->get('/ciudadano'), $this->comoCiudadano()->get('/ciudadano/nueva/1'), $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}"),
            $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app'), $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$e->id}"),
            $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/nuevo'), $this->comoFuncionario($this->usuario('admin'))->get('/app/diagnostico'), $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/codificador'),
            $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/pisee'), $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app/comunicaciones'),
        ];
        foreach ($paginas as $i => $r) {
            $r->assertOk();
            $html = $r->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, "script en línea en página #$i");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html, "handler en línea en página #$i");
            $this->assertDoesNotMatchRegularExpression('#(src|href)=["\']https?://(?!localhost)#i', $html, "recurso externo en página #$i");
            $this->assertStringContainsString('Saltar al contenido', $html);
        }
    }

    public function test_cache_control_no_store_en_contenido_autenticado(): void
    {
        $this->comoCiudadano()->get('/ciudadano')->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_cookie_de_sesion_host_prefix_httponly_secure_lax(): void
    {
        config(['session.driver' => 'cookie']);
        $c = $this->get('/ingreso')->headers->getCookies();
        $this->assertNotEmpty($c);
        $ses = collect($c)->first(fn ($x) => $x->getName() === '__Host-sgd_session');
        $this->assertNotNull($ses);
        $this->assertTrue($ses->isSecure());
        $this->assertTrue($ses->isHttpOnly());
        $this->assertSame('lax', $ses->getSameSite());
        $this->assertSame('/', $ses->getPath());
        $this->assertNull($ses->getDomain());
    }

    public function test_idor_ciudadano_ajeno_no_ve_solicitud_ni_comprobante(): void
    {
        $s = $this->solicitudDOM();
        $otro = ['run' => '20000002-1', 'nombres' => 'Otro', 'apellidos' => 'Vecino'];
        $sesion = ['ciudadano' => $otro, 'login_at' => time(), 'last_seen' => time()];
        $this->withSession($sesion)->get("/ciudadano/solicitudes/{$s->folio}")->assertNotFound();
        $this->withSession($sesion)->get("/ciudadano/solicitudes/{$s->folio}/comprobante")->assertNotFound();
        $this->withSession($sesion)->post("/ciudadano/solicitudes/{$s->folio}/responder", ['respuesta' => 'hola mundo'])->assertNotFound();
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->assertOk();
    }

    public function test_ciudadano_sin_sesion_y_ciudadano_no_entra_a_app(): void
    {
        $this->get('/ciudadano')->assertRedirect('/ingreso');
        $this->comoCiudadano()->get('/app')->assertRedirect('/ingreso');
    }

    public function test_control_de_acceso_expedientes_por_rol(): void
    {
        $s = $this->solicitudDOM();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->usuario('funcionario', 1));
        $url = "/app/expedientes/{$e->id}";
        $this->comoFuncionario($this->usuario('funcionario', 1))->get($url)->assertOk();
        $this->comoFuncionario($this->usuario('funcionario', 2))->get($url)->assertOk(); // misma unidad
        $this->comoFuncionario($this->usuario('funcionario', 3))->get($url)->assertForbidden(); // DIDECO
        $this->comoFuncionario($this->usuario('admin'))->get($url)->assertForbidden(); // el admin NO lee expedientes
        $this->comoFuncionario($this->usuario('admin'))->get($url.'/exportar')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get($url)->assertOk(); // auditor: lectura
        $this->comoFuncionario($this->usuario('auditor'))->post($url.'/comentarios', ['texto' => 'x', 'tipo' => 'comentario'])->assertForbidden(); // solo lectura
        $this->comoFuncionario($this->usuario('auditor'))->post($url.'/cerrar')->assertForbidden();
        // derivación otorga acceso
        $this->comoFuncionario($this->usuario('funcionario', 1))->post($url.'/derivar', ['a_usuario_id' => $this->usuario('funcionario', 3)->id, 'motivo' => 'revisar'])->assertRedirect();
        $this->comoFuncionario($this->usuario('funcionario', 3))->get($url)->assertOk();
    }

    public function test_idor_de_archivos_de_otro_expediente(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e1 = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $s2 = $this->solicitudDOM();
        $e2 = app(ExpedienteService::class)->crearDesdeSolicitud($s2, $dom);
        $archivoDeE2 = $e2->archivos()->first();
        $this->comoFuncionario($dom)->get("/app/expedientes/{$e1->id}/archivos/{$archivoDeE2->id}")->assertNotFound();
        $this->comoFuncionario($dom)->get("/app/expedientes/{$e2->id}/archivos/{$archivoDeE2->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_admin_gestiona_mantenedores_y_otros_no(): void
    {
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/usuarios')->assertOk();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/usuarios')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/admin/usuarios')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/diagnostico')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/seguridad')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/seguridad')->assertOk();
        $this->comoFuncionario($this->usuario('auditor'))->post('/app/seguridad/incidentes', ['titulo' => 'x', 'descripcion' => 'y', 'severidad' => 'baja'])->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/diagnostico')->assertOk()->assertSee('Extensión PHP sodium');
    }

    public function test_csrf_obligatorio_en_peticiones_no_seguras(): void
    {
        $this->app['env'] = 'local'; // el middleware CSRF se omite solo en entorno de pruebas
        $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => 1])->assertStatus(419);
        $this->comoFuncionario($this->usuario('funcionario'))->post('/salir')->assertStatus(419);
        $this->assertDatabaseHas('audit_log', ['accion' => 'seguridad.csrf_fallido']);
        // con token válido pasa el filtro CSRF
        $this->withSession(['_token' => 'tok'])->post('/salir', ['_token' => 'tok'])->assertRedirect('/');
    }

    public function test_xss_se_escapa_en_vistas(): void
    {
        $t = Tramite::query()->with('anexos')->find(2);
        $s = app(SolicitudService::class)->ingresar($this->ciudadano(), $t, 'a@b.cl', '<script>alert("x")</script><img src=x onerror=alert(1)> detalle largo', 'correo', [4 => $this->archivo()], []);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->usuario('funcionario', 1));
        $html = $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$e->id}")->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert', $html);
        $html = $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->getContent();
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_rate_limit_login_y_validacion_publica(): void
    {
        RateLimiter::clear('login');
        for ($i = 0; $i < 10; $i++) {
            $this->get('/ingreso/ciudadano')->assertRedirect();
        }
        $this->get('/ingreso/ciudadano')->assertStatus(429);
        for ($i = 0; $i < 30; $i++) {
            $this->get('/validar/SDD-AAAA-BBBB-CCCC')->assertOk();
        }
        $this->get('/validar/SDD-AAAA-BBBB-CCCC')->assertStatus(429);
    }

    public function test_sesion_expira_por_inactividad_y_de_forma_absoluta(): void
    {
        $this->withSession(['ciudadano' => $this->ciudadano(), 'login_at' => time(), 'last_seen' => time() - 31 * 60])->get('/ciudadano')->assertRedirect('/ingreso');
        $this->withSession(['ciudadano' => $this->ciudadano(), 'login_at' => time() - 9 * 3600, 'last_seen' => time()])->get('/ciudadano')->assertRedirect('/ingreso');
        $this->withSession(['ciudadano' => $this->ciudadano(), 'login_at' => time() - 3600, 'last_seen' => time() - 60])->get('/ciudadano')->assertOk();
    }

    public function test_sesion_en_bd_guarda_solo_el_hash_del_token(): void
    {
        config(['session.driver' => 'sgd_database']);
        $h = app('session')->driver()->getHandler();
        $this->assertInstanceOf(HashedDatabaseSessionHandler::class, $h);
        $id = str_repeat('a', 40);
        $h->write($id, serialize(['x' => 1]));
        $this->assertDatabaseHas('sessions', ['id' => hash('sha256', $id)]);
        $this->assertDatabaseMissing('sessions', ['id' => $id]);
        $this->assertSame(serialize(['x' => 1]), $h->read($id));
    }

    private function loginDev(string $via, string $run, string $state = 'estado-de-prueba', array $extra = [])
    {
        $cu = ['state' => hash('sha256', $state), 'via' => $via, 'exp' => time() + 600];

        return $this->withSession(['cu' => $cu] + $extra)->post('/dev/login', ['run' => $run, 'nombres' => 'X', 'apellidos' => 'Y', 'via' => $via, 'state' => $state]);
    }

    public function test_login_state_de_un_solo_uso_y_sesion_nueva(): void
    {
        $r = $this->get('/ingreso/ciudadano');
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertGreaterThanOrEqual(64, strlen($q['state'])); // 256 bits
        $r->assertSessionHas('cu');
        // state incorrecto o ausente
        $this->get('/claveunica/callback?code=abc&state=malo')->assertRedirect('/ingreso')->assertSessionHas('error');
        $this->assertGuest();
        // login válido: la sesión previa se destruye (anti fijación) y se crea una nueva
        $this->loginDev('ciudadano', '20.000.001-3', 'estado-de-prueba', ['marker' => 'sesion-previa'])->assertRedirect('/ciudadano');
        $this->assertNull(session('marker'));
        $this->assertNotNull(session('ciudadano'));
        $this->assertNotNull(session('login_at'));
        // state ya consumido / sesión sin state: rechazado
        $this->withSession([])->post('/dev/login', ['run' => '20.000.001-3', 'nombres' => 'X', 'apellidos' => 'Y', 'via' => 'ciudadano', 'state' => 'estado-de-prueba'])->assertRedirect('/ingreso');
    }

    public function test_funcionario_debe_existir_y_estar_activo(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $this->loginDev('funcionario', $dom->run)->assertRedirect('/espacio');
        $this->assertAuthenticatedAs($dom);
        $dom->forceFill(['activo' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->loginDev('funcionario', $dom->run)->assertRedirect('/ingreso')->assertSessionHas('error');
        $this->loginDev('funcionario', '10000099-'.Run::dv('10000099'))->assertRedirect('/ingreso'); // RUN inexistente
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.fallo']);
    }

    public function test_usuario_desactivado_pierde_acceso_inmediatamente(): void
    {
        $u = $this->usuario('funcionario', 1);
        $this->comoFuncionario($u)->get('/app')->assertOk();
        $u->forceFill(['activo' => false])->save();
        $this->comoFuncionario($u)->get('/app')->assertRedirect('/ingreso');
    }

    public function test_pagina_de_error_generica_sin_detalles_internos(): void
    {
        config(['app.debug' => false]);
        $r = $this->get('/ruta-que-no-existe');
        $r->assertNotFound();
        $this->assertStringNotContainsString('vendor/', $r->getContent());
        $this->assertStringNotContainsString('Stack trace', $r->getContent());
    }

    public function test_dotfiles_y_extensiones_peligrosas_bloqueadas_en_htaccess(): void
    {
        $h = file_get_contents(base_path('public/.htaccess'));
        foreach (['RewriteCond %{HTTPS} off', 'Options -Indexes', '\.(sql|env|log|bak', 'X-Content-Type-Options', 'Content-Security-Policy'] as $needle) {
            $this->assertStringContainsString($needle, $h);
        }
    }

    public function test_produccion_falla_cerrada_con_configuracion_insegura(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://sgd.santodomingo.cl', 'sgd.claveunica.mode' => 'real', 'sgd.firmagob.mode' => 'prod',
            'mail.default' => 'log', 'sgd.llm.enabled' => false]);
        ProductionGuard::verificar(); // configuración correcta: no lanza
        foreach ([['sgd.firmagob.mode', 'mock'], ['sgd.claveunica.mode', 'mock'], ['app.debug', true], ['app.url', 'http://x.cl'], ['sgd.audit_hmac_key', 'corta'], ['sgd.file_keys', '']] as [$k, $v]) {
            $prev = config($k);
            config([$k => $v]);
            try {
                ProductionGuard::verificar();
                $this->fail("Debió negarse a arrancar con $k=".json_encode($v));
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('insegura', $e->getMessage());
            }
            config([$k => $prev]);
        }
    }
}
