<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Revisión de rutas (OWASP A01/A04/A05): cada ruta interna exige sesión, las que cambian datos no son GET y las públicas tienen límite de uso. */
class SeguridadRutasTest extends TestCase
{
    use Escenario, RefreshDatabase;

    /** GET públicos sin límite propio (portada, recursos estáticos de marca o retornos de proveedores). */
    private const SIN_LIMITE = ['/', 'accesibilidad', 'ayuda', 'ayuda/{perfil}', 'privacidad', 'ingreso', 'ingreso/pruebas', 'login', 'login/usuario', 'logout', 'espacio', 'publico', 'marca/estilo.css', 'marca/logo',
        'ingreso/auto', 'ingreso/ciudadano', 'ingreso/funcionario', 'tramites', 'tramites/{id}/{slug?}', 'validar', 'dev/login', 'pagos-proveedores/ingresar', 'salir'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_toda_ruta_interna_exige_sesion_de_funcionario_y_las_del_vecino_su_sesion(): void
    {
        foreach (Route::getRoutes() as $r) {
            $mw = $r->gatherMiddleware();
            if (str_starts_with($r->uri(), 'app')) {
                $this->assertContains('funcionario', $mw, 'Sin sesión de funcionario: '.$r->uri());
            }
            if (str_starts_with($r->uri(), 'ciudadano')) {
                $this->assertContains('ciudadano', $mw, 'Sin sesión de vecino: '.$r->uri());
            }
            if (str_starts_with($r->uri(), 'app/admin')) {
                $this->assertNotEmpty(array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'permiso:')), 'Administración sin permiso: '.$r->uri());
            }
        }
    }

    public function test_ninguna_ruta_get_cambia_datos_y_todas_las_demas_pasan_por_csrf(): void
    {
        $mutan = '#(eliminar|borrar|delete|desactivar|reenviar|reintentar|sincronizar|anular|purgar|firmar|aprobar|cerrar|derivar|guardar)#i';
        foreach (Route::getRoutes() as $r) {
            $mw = $r->gatherMiddleware();
            $metodos = array_diff($r->methods(), ['HEAD', 'OPTIONS']);
            if ($metodos === ['GET']) {
                $this->assertDoesNotMatchRegularExpression($mutan, $r->uri(), 'Una ruta GET parece cambiar datos: '.$r->uri());
            } elseif (count($metodos) < 3) { // las rutas de redirección aceptan todos los métodos y no hacen nada
                $this->assertContains('web', $mw, 'Sin grupo web (CSRF): '.$r->uri());
            }
        }
        $this->assertNull(Route::getRoutes()->getByName('storage.local'), 'El servidor de archivos integrado de Laravel debe estar desactivado');
    }

    public function test_las_rutas_publicas_con_parametros_o_busqueda_tienen_limite_de_uso(): void
    {
        foreach (Route::getRoutes() as $r) {
            $mw = $r->gatherMiddleware();
            if (str_starts_with($r->uri(), 'app') || str_starts_with($r->uri(), 'ciudadano') || in_array($r->uri(), self::SIN_LIMITE, true) || str_starts_with($r->uri(), '_') || $r->uri() === 'up') {
                continue;
            }
            $this->assertNotEmpty(array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')) ?: array_filter($mw, fn ($m) => is_string($m) && ($m === 'ciudadano' || $m === 'funcionario')),
                'Ruta pública sin límite de uso: '.implode('|', $r->methods()).' '.$r->uri());
        }
    }

    public function test_el_cierre_de_sesion_get_no_se_dispara_desde_imagenes_ni_marcos_de_otro_sitio(): void
    {
        $u = $this->usuario('funcionario');
        foreach (['image', 'iframe', 'script', 'style'] as $dest) {
            $this->actingAs($u)->withHeaders(['Sec-Fetch-Dest' => $dest, 'Sec-Fetch-Site' => 'cross-site'])->get('/logout');
            $this->assertAuthenticatedAs($u);
        }
        $this->actingAs($u)->withHeaders(['Sec-Fetch-Dest' => 'document', 'Sec-Fetch-Site' => 'cross-site'])->get('/logout')->assertRedirect(route('inicio'));
        $this->assertGuest();
    }

    public function test_rutas_de_desarrollo_y_de_pruebas_no_existen_en_produccion(): void
    {
        config(['sgd.ambiente' => 'produccion', 'sgd.claveunica.mode' => 'real']);
        $this->get('/ingreso/pruebas')->assertNotFound();
        $this->post('/ingreso/pruebas', ['run' => '1-9', 'password' => 'x'])->assertNotFound();
    }
}
