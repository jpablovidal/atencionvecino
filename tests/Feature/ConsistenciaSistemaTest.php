<?php

namespace Tests\Feature;

use App\Services\Admin\MenuService;
use App\Support\Permisos;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Consistencia transversal: rutas, permisos, menú, configuración y documentación. */
class ConsistenciaSistemaTest extends TestCase
{
    public function test_toda_ruta_de_app_y_ciudadano_exige_sesion_y_las_publicas_son_las_esperadas(): void
    {
        $publicas = [];
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            $mw = $r->gatherMiddleware();
            if (str_starts_with($uri, 'app/') || $uri === 'app') {
                $this->assertContains('funcionario', $mw, "$uri sin middleware funcionario");
            } elseif (str_starts_with($uri, 'ciudadano')) {
                $this->assertContains('ciudadano', $mw, "$uri sin middleware ciudadano");
            } elseif (! str_starts_with($uri, '_') && ! str_starts_with($uri, 'up') && ! str_starts_with($uri, 'storage')) {
                $publicas[] = $r->methods()[0].' /'.ltrim($uri, '/');
            }
        }
        sort($publicas);
        $esperadas = ['GET /', 'GET /accesibilidad', 'GET /ayuda', 'GET /ayuda/{perfil}', 'GET /claveunica/callback', 'GET /dev/login', 'GET /ingreso', 'GET /ingreso/pruebas', 'GET /ingreso/{via}',
            'GET /marca/estilo.css', 'GET /marca/logo', 'GET /publico', 'GET /publico/{codigo}', 'GET /validar', 'GET /validar-comprobante/{folio}/{codigo}', 'GET /validar/{codigo}', 'GET /validar/{codigo}/copia', 'POST /validar', 'POST /dev/login',
            'POST /ingreso/pruebas', 'POST /salir'];
        $inesperadas = array_diff($publicas, $esperadas);
        $this->assertSame([], array_values($inesperadas), 'rutas públicas no previstas: '.implode(', ', $inesperadas));
    }

    public function test_todo_permiso_usado_en_rutas_y_controladores_existe_en_el_catalogo(): void
    {
        $cat = array_keys(Permisos::catalogo());
        $usados = [];
        foreach (Route::getRoutes() as $r) {
            foreach ($r->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'permiso:')) {
                    foreach (explode(',', substr($m, 8)) as $p) {
                        $usados[$p] = $r->uri();
                    }
                }
            }
        }
        foreach (['app/Http/Controllers', 'app/Services'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->getExtension() === 'php' && preg_match_all("/permiso\\('([a-z_.]+)'\\)|'permiso' => '([a-z_.]+)'/", (string) file_get_contents($f->getPathname()), $m)) {
                    foreach (array_filter(array_merge($m[1], $m[2])) as $p) {
                        $usados[$p] = $f->getFilename();
                    }
                }
            }
        }
        foreach ($usados as $p => $donde) {
            $this->assertContains($p, $cat, "permiso «{$p}» usado en $donde no está en el catálogo");
        }
        $this->assertGreaterThan(20, count($usados));
        // cada permiso del catálogo se aplica en alguna parte (sin permisos huérfanos)
        $huerfanos = array_diff($cat, array_keys($usados), ['llm.usar', 'incidentes.gestionar']);
        $codigo = '';
        foreach (['app', 'resources/views', 'routes'] as $d) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($d), \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (in_array($f->getExtension(), ['php'], true)) {
                    $codigo .= file_get_contents($f->getPathname());
                }
            }
        }
        foreach ($huerfanos as $p) {
            $this->assertStringContainsString("'$p'", $codigo, "permiso «{$p}» del catálogo no se usa en ninguna parte");
        }
    }

    public function test_el_menu_solo_referencia_rutas_y_permisos_existentes(): void
    {
        $cat = array_keys(Permisos::catalogo());
        $rp = new \ReflectionMethod(MenuService::class, 'sistema');
        $items = $rp->invoke(app(MenuService::class));
        foreach ($items as $clave => $d) {
            [$ruta] = explode(':', $d['ruta']);
            $this->assertTrue(Route::has($ruta), "ítem de menú «{$clave}» apunta a una ruta inexistente: $ruta");
            if ($d['permiso']) {
                $this->assertContains($d['permiso'], $cat, "ítem «{$clave}» exige un permiso inexistente");
            }
        }
    }

    public function test_cada_variable_de_entorno_de_sgd_esta_documentada_en_env_example(): void
    {
        $env = (string) file_get_contents(base_path('.env.example'));
        preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents(base_path('config/sgd.php')), $m);
        $faltan = [];
        foreach (array_unique($m[1]) as $k) {
            if (! preg_match('/^#?\s*'.preg_quote($k, '/').'=/m', $env)) {
                $faltan[] = $k;
            }
        }
        $this->assertSame([], $faltan, 'variables sin documentar en .env.example: '.implode(', ', $faltan));
    }

    public function test_los_manuales_y_documentos_mencionan_las_funciones_principales(): void
    {
        $admin = (string) file_get_contents(base_path('resources/manuales/administrador.md'));
        foreach (['Apariencia', 'Barra de menú', 'Roles y permisos', 'APIs e integraciones', 'Folios', 'Repositorio', 'Registro de logs', 'Usuarios conectados', 'Plantillas', 'Recursos del servidor', 'Componentes y librerías'] as $t) {
            $this->assertStringContainsString($t, $admin, "el manual del administrador no menciona «{$t}»");
        }
        foreach (['SEGURIDAD', 'LIMITACIONES', 'DECISIONES', 'ACCESIBILIDAD', 'PENTEST'] as $d) {
            $this->assertFileExists(base_path("docs/$d.md"));
        }
    }
}
