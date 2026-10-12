<?php

namespace App\Services\Sistema;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de plataforma, librerías PHP y front-end con sus versiones, y verificación de recursos externos (CDN).
 * Solo lee archivos locales: no consulta Internet.
 */
class ComponentesService
{
    /** Activos front-end autoalojados: nombre => [archivo, regex de versión, paquete de origen, licencia, uso]. */
    private const FRONTEND = [
        'jQuery' => ['js/jquery.min.js', '/jQuery v([\d.]+)/', 'jquery', 'MIT', 'Dependencia de Bootstrap 4'],
        'Bootstrap (JS + Popper)' => ['js/bootstrap.bundle.min.js', '/Bootstrap v([\d.]+)/', 'bootstrap', 'MIT', 'Componentes interactivos'],
        'Sistema de diseño gob.cl (CSS, Bootstrap 4.5.3 base)' => ['css/gob.cl.css', '/Bootstrap v([\d.]+)/', '@gobdigital-cl/gob.cl', 'MIT', 'Estilos del Gobierno de Chile'],
        'TinyMCE' => ['tinymce/tinymce.min.js', '/majorVersion:"(\d+)",minorVersion:"([\d.]+)"/', 'tinymce', 'GPL-2.0-or-later', 'Editor de documentos y plantillas'],
        'Idioma español de TinyMCE' => ['tinymce/langs/es.js', null, 'tinymce-i18n', 'A VALIDAR (ver LICENSE del paquete)', 'Traducción del editor'],
        'Roboto (400)' => ['fonts/roboto-latin-400-normal.woff2', null, '@fontsource/roboto', 'Apache-2.0', 'Tipografía'],
        'Roboto (500)' => ['fonts/roboto-latin-500-normal.woff2', null, '@fontsource/roboto', 'Apache-2.0', 'Tipografía'],
        'Roboto (700)' => ['fonts/roboto-latin-700-normal.woff2', null, '@fontsource/roboto', 'Apache-2.0', 'Tipografía'],
        'Iconos gob.cl (fuente)' => ['fonts/gob-cl.woff2', null, '@gobdigital-cl/gob.cl', 'MIT', 'Iconografía'],
        'sgd.js (propio)' => ['js/sgd.js', null, 'sgd-santo-domingo', 'Propia', 'Confirmaciones, editor, asistente de redacción'],
        'a11y.js (propio)' => ['js/a11y.js', null, 'sgd-santo-domingo', 'Propia', 'Barra de accesibilidad'],
        'sgd.css (propio)' => ['css/sgd.css', null, 'sgd-santo-domingo', 'Propia', 'Estilos del sistema'],
    ];

    /** @return array<string,mixed> */
    public function plataforma(): array
    {
        $db = ['driver' => DB::connection()->getDriverName(), 'version' => null];
        try {
            $db['version'] = $db['driver'] === 'sqlite' ? (string) (DB::selectOne('select sqlite_version() as v')->v ?? '') : (string) (DB::selectOne('select version() as v')->v ?? '');
        } catch (\Throwable) {
        }
        $libs = ['OpenSSL' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null, 'cURL' => function_exists('curl_version') ? (curl_version()['version'] ?? null) : null,
            'libsodium' => defined('SODIUM_LIBRARY_VERSION') ? SODIUM_LIBRARY_VERSION : null, 'GD' => defined('GD_VERSION') ? GD_VERSION : null,
            'ICU (intl)' => defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null, 'PCRE' => defined('PCRE_VERSION') ? PCRE_VERSION : null,
            'libxml' => defined('LIBXML_DOTTED_VERSION') ? LIBXML_DOTTED_VERSION : null, 'zlib' => defined('ZLIB_VERSION') ? ZLIB_VERSION : null];
        $ext = get_loaded_extensions();
        sort($ext, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'zend' => zend_version(), 'laravel' => app()->version(), 'entorno' => app()->environment(), 'bd' => $db,
            'so' => php_uname('s').' '.php_uname('r'), 'servidor_web' => $_SERVER['SERVER_SOFTWARE'] ?? null, 'bibliotecas' => array_filter($libs),
            'extensiones' => array_map(fn ($e) => ['nombre' => $e, 'version' => phpversion($e) ?: null], $ext),
        ];
    }

    /** @return array<int,array{nombre:string,version:string,licencia:string,tipo:string,dev:bool,fecha:?string,descripcion:?string}> */
    public function paquetes(): array
    {
        $f = base_path('vendor/composer/installed.json');
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (! is_array($j)) {
            return [];
        }
        $dev = array_flip($j['dev-package-names'] ?? []);
        $out = [];
        foreach ($j['packages'] ?? [] as $p) {
            $out[] = ['nombre' => $p['name'], 'version' => ltrim((string) ($p['version'] ?? ''), 'v'), 'licencia' => implode(', ', (array) ($p['license'] ?? [])) ?: 'no declarada',
                'tipo' => (string) ($p['type'] ?? 'library'), 'dev' => isset($dev[$p['name']]), 'fecha' => isset($p['time']) ? substr((string) $p['time'], 0, 10) : null,
                'descripcion' => isset($p['description']) ? mb_substr((string) $p['description'], 0, 160) : null];
        }
        usort($out, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function frontend(): array
    {
        $out = [];
        foreach (self::FRONTEND as $nombre => [$arch, $re, $paquete, $lic, $uso]) {
            $ruta = public_path('assets/'.$arch);
            $existe = is_file($ruta);
            $version = null;
            if ($existe && $re) {
                $txt = (string) file_get_contents($ruta, false, null, 0, 600000);
                if (preg_match($re, $txt, $m)) {
                    $version = count($m) > 2 ? $m[1].'.'.$m[2] : $m[1];
                }
            }
            $out[] = ['nombre' => $nombre, 'archivo' => 'assets/'.$arch, 'version' => $version, 'paquete' => $paquete, 'licencia' => $lic, 'uso' => $uso,
                'tamano' => $existe ? (int) filesize($ruta) : null, 'sha256' => $existe ? hash_file('sha256', $ruta) : null, 'presente' => $existe, 'origen' => 'autoalojado'];
        }

        return $out;
    }

    /** @return list<string> plugins de TinyMCE instalados */
    public function pluginsTiny(): array
    {
        $d = public_path('assets/tinymce/plugins');

        return is_dir($d) ? array_values(array_map('basename', array_filter(glob($d.'/*') ?: [], 'is_dir'))) : [];
    }

    /**
     * Busca recursos cargados desde otros orígenes (CDN). «cargados» debe estar vacío; «enlaces» son hipervínculos de navegación.
     *
     * @return array{cargados:array<int,array{archivo:string,url:string}>,enlaces:array<int,array{archivo:string,url:string}>,archivos_revisados:int,csp:string}
     */
    public function externos(): array
    {
        $cargados = [];
        $enlaces = [];
        $revisados = 0;
        $archivos = [];
        foreach ($this->recorrer(resource_path('views'), ['php']) as $f) {
            $archivos[] = $f;
        }
        foreach ($this->recorrer(public_path('assets'), ['css', 'js', 'svg', 'html']) as $f) {
            $archivos[] = $f;
        }
        foreach ($archivos as $f) {
            if (filesize($f) > 3_000_000) {
                continue;
            }
            $revisados++;
            $t = (string) file_get_contents($f);
            $rel = ltrim(str_replace(base_path(), '', $f), '/');
            $esVista = str_ends_with($f, '.blade.php');
            // etiquetas que CARGAN recursos
            if (preg_match_all('/<(?:script|link|img|iframe|source|video|audio|embed|object|input)\b[^>]*?\b(?:src|href|data|poster)\s*=\s*["\'](?:https?:)?\/\/([^"\'\s>]+)/i', $t, $m)) {
                foreach ($m[0] as $i => $tag) {
                    if (preg_match('/^<link\b/i', $tag) && ! preg_match('/rel\s*=\s*["\']?(stylesheet|preload|icon|modulepreload|prefetch)/i', $tag)) {
                        continue; // <link rel="canonical"> etc. no carga recursos
                    }
                    $cargados[] = ['archivo' => $rel, 'url' => $this->recortar($m[1][$i])];
                }
            }
            // CSS: @import y url() externos
            if (preg_match_all('/(?:@import\s+(?:url\()?|url\()\s*["\']?(?:https?:)?\/\/([^"\')\s]+)/i', $t, $m) && (str_ends_with($f, '.css') || $esVista || str_ends_with($f, '.svg'))) {
                foreach ($m[1] as $u) {
                    $cargados[] = ['archivo' => $rel, 'url' => $this->recortar($u)];
                }
            }
            // JS propio: llamadas de red a otros orígenes
            if ($esVista === false && str_ends_with($f, '.js') && ! str_contains($f, '/tinymce/') && ! str_ends_with($f, '.min.js') && preg_match_all('/(?:fetch|import|XMLHttpRequest[^;]*open)\s*\(\s*["\'](?:https?:)?\/\/([^"\']+)/i', $t, $m)) {
                foreach ($m[1] as $u) {
                    $cargados[] = ['archivo' => $rel, 'url' => $this->recortar($u)];
                }
            }
            // hipervínculos (informativos)
            if ($esVista && preg_match_all('/<a\b[^>]*?\bhref\s*=\s*["\']https?:\/\/([^"\'\s>]+)/i', $t, $m)) {
                foreach ($m[1] as $u) {
                    $enlaces[] = ['archivo' => $rel, 'url' => $this->recortar($u)];
                }
            }
        }
        $csp = '';
        try {
            $r = (new SecurityHeaders)->handle(Request::create('/'), fn () => response(''));
            $csp = (string) $r->headers->get('Content-Security-Policy');
        } catch (\Throwable) {
        }

        return ['cargados' => $cargados, 'enlaces' => $enlaces, 'archivos_revisados' => $revisados, 'csp' => $csp];
    }

    /** Hosts de servicios externos a los que el SERVIDOR se conecta (integraciones; no son CDN). @return array<string,string> */
    public function integraciones(): array
    {
        $out = [];
        foreach (['claveunica' => 'ClaveÚnica', 'firmagob' => 'FirmaGob', 'docdigital' => 'DocDigital', 'pisee' => 'PISEE', 'codificador' => 'Codificador', 'llm' => 'Asistente de redacción'] as $k => $n) {
            $c = (array) config('sgd.'.$k);
            foreach ($c as $clave => $v) {
                if (is_string($v) && preg_match('#^https?://([^/\s]+)#', $v, $m) && ! str_contains((string) $clave, 'secret')) {
                    $out[$n.' ('.$clave.')'] = $m[1];
                }
            }
        }
        $out['Google (Gmail API / Drive)'] = 'oauth2.googleapis.com, www.googleapis.com (según configuración)';

        return $out;
    }

    /** @return array<string,mixed> */
    public function inventario(): array
    {
        return ['generado_en' => now()->toIso8601String(), 'plataforma' => $this->plataforma(), 'paquetes_php' => $this->paquetes(), 'frontend' => $this->frontend(),
            'plugins_tinymce' => $this->pluginsTiny(), 'recursos_externos' => $this->externos(), 'integraciones_servidor' => $this->integraciones()];
    }

    /** @return list<string> */
    private function recorrer(string $dir, array $ext): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && in_array(strtolower($f->getExtension()), $ext, true)) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function recortar(string $u): string
    {
        return mb_substr(strtok($u, '?#') ?: $u, 0, 140);
    }
}
