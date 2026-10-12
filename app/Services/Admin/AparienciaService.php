<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\ConfigSitio;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Contraste;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * Apariencia: colores, encabezado, pie y logo. Todo se valida (contraste WCAG ≥ 4,5:1 en cada par texto/fondo, URLs seguras,
 * logo re-codificado para eliminar metadatos y contenido incrustado) y se publica por CSS/imagen servidos desde la propia app (CSP).
 */
class AparienciaService
{
    private const CACHE = 'sgd_apariencia_v1';

    public const DEFECTO = [
        'colores' => [
            'primario' => '#006fb3', 'titulo' => '#0a2e4d',
            'header_fondo' => '#006fb3', 'header_texto' => '#ffffff',
            'nav_fondo' => '#0a2e4d', 'nav_texto' => '#ffffff',
            'footer_fondo' => '#0a2e4d', 'footer_texto' => '#ffffff', 'footer_enlace' => '#cfe6f7',
        ],
        'header' => ['titulo' => 'I. Municipalidad de Santo Domingo', 'subtitulo' => 'Sistema de Gestión Documental', 'mostrar_logo' => true, 'logo_alt' => 'Logo de la Municipalidad de Santo Domingo', 'logo_alto_px' => 48],
        'footer' => ['texto' => 'I. Municipalidad de Santo Domingo · Ley 21.180 de Transformación Digital del Estado', 'texto_legal' => '', 'enlaces' => []],
        'logo' => null,
    ];

    /** Pares (texto, fondo) que deben cumplir 4,5:1. */
    private const PARES = [['header_texto', 'header_fondo', 'el texto del encabezado'], ['nav_texto', 'nav_fondo', 'el texto del menú'], ['footer_texto', 'footer_fondo', 'el texto del pie'],
        ['footer_enlace', 'footer_fondo', 'los enlaces del pie']];

    public function __construct(private readonly AuditoriaService $audit) {}

    /** @return array<string,mixed> */
    public function datos(): array
    {
        return Cache::remember(self::CACHE, 300, function () {
            $v = ConfigSitio::query()->where('clave', 'apariencia')->value('valor');
            $j = $v ? (array) json_decode($v, true) : [];

            return array_replace_recursive(self::DEFECTO, array_intersect_key($j, self::DEFECTO)) + ['version' => substr(md5((string) $v), 0, 10)];
        });
    }

    /** @param array<string,mixed> $d */
    public function guardar(array $d, Usuario $u): void
    {
        $actual = $this->datos();
        $c = [];
        foreach (array_keys(self::DEFECTO['colores']) as $k) {
            $v = strtolower(trim((string) ($d['colores'][$k] ?? '')));
            if (! Contraste::esHex($v)) {
                throw new FlujoException("Color «{$k}» inválido: use el formato #RRGGBB.");
            }
            $c[$k] = $v;
        }
        foreach (self::PARES as [$t, $f, $que]) {
            $r = Contraste::ratio($c[$t], $c[$f]);
            if ($r < 4.5) {
                throw new FlujoException('Contraste insuficiente para '.$que.' ('.number_format($r, 1, ',', '').':1; el mínimo de accesibilidad es 4,5:1).');
            }
        }
        if (Contraste::ratio('#ffffff', $c['primario']) < 4.5) {
            throw new FlujoException('El color primario debe tener contraste ≥ 4,5:1 con el texto blanco de los botones.');
        }
        if (Contraste::ratio($c['titulo'], '#f7f9fb') < 4.5) {
            throw new FlujoException('El color de los títulos debe tener contraste ≥ 4,5:1 sobre el fondo de página.');
        }
        $h = (array) ($d['header'] ?? []);
        $header = [
            'titulo' => $this->texto($h['titulo'] ?? '', 'título', 1, 120), 'subtitulo' => $this->texto($h['subtitulo'] ?? '', 'subtítulo', 0, 120),
            'mostrar_logo' => (bool) ($h['mostrar_logo'] ?? false), 'logo_alt' => $this->texto($h['logo_alt'] ?? '', 'texto alternativo del logo', 0, 150),
            'logo_alto_px' => max(24, min(120, (int) ($h['logo_alto_px'] ?? 48))),
        ];
        if ($header['mostrar_logo'] && $header['logo_alt'] === '') {
            throw new FlujoException('Indique el texto alternativo del logo (accesibilidad).');
        }
        $f = (array) ($d['footer'] ?? []);
        $enlaces = [];
        foreach (array_slice((array) ($f['enlaces'] ?? []), 0, 8) as $e) {
            $et = trim((string) ($e['etiqueta'] ?? ''));
            $url = trim((string) ($e['url'] ?? ''));
            if ($et === '' && $url === '') {
                continue;
            }
            $enlaces[] = ['etiqueta' => $this->texto($et, 'etiqueta del enlace', 1, 60), 'url' => self::urlSegura($url)];
        }
        $footer = ['texto' => $this->texto($f['texto'] ?? '', 'texto del pie', 0, 300), 'texto_legal' => $this->texto($f['texto_legal'] ?? '', 'texto legal', 0, 400), 'enlaces' => $enlaces];
        $nuevo = ['colores' => $c, 'header' => $header, 'footer' => $footer, 'logo' => $actual['logo']];
        $this->persistir($nuevo, $u);
        $this->audit->registrar('apariencia.guardar', 'config_sitio', 'apariencia');
    }

    /** Enlace interno ("/ruta") o https://; nunca javascript:, data:, http:, ni protocolo relativo. */
    public static function urlSegura(string $url): string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 255 || preg_match('/[\s\x00-\x1f"<>\\\\]/', $url)) {
            throw new FlujoException('Enlace inválido (sin espacios ni caracteres especiales).');
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }
        $p = parse_url($url);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new FlujoException('Los enlaces deben ser rutas internas (/…) o direcciones https:// sin credenciales.');
        }

        return $url;
    }

    private function texto(mixed $v, string $que, int $min, int $max): string
    {
        $v = trim((string) $v);
        if (mb_strlen($v) < $min || mb_strlen($v) > $max || preg_match('/[\r\n\0]/', $v)) {
            throw new FlujoException("El campo «{$que}» debe tener entre {$min} y {$max} caracteres, en una línea.");
        }

        return $v;
    }

    /** @param array<string,mixed> $nuevo */
    private function persistir(array $nuevo, Usuario $u): void
    {
        ConfigSitio::query()->updateOrCreate(['clave' => 'apariencia'], ['valor' => (string) json_encode($nuevo, JSON_UNESCAPED_UNICODE), 'actualizado_por' => $u->id]);
        Cache::forget(self::CACHE);
    }

    public function restablecer(Usuario $u): void
    {
        $l = $this->datos()['logo'];
        $this->persistir(['logo' => $l] + self::DEFECTO, $u);
        $this->audit->registrar('apariencia.restablecer', 'config_sitio', 'apariencia');
    }

    // ------------------------------------------------------------------ Logo
    private function dirLogo(): string
    {
        return rtrim((string) config('sgd.storage_path'), '/').'/marca';
    }

    public function subirLogo(UploadedFile $f, Usuario $u): void
    {
        if (! $f->isValid() || $f->getSize() === 0 || $f->getSize() > 1024 * 1024) {
            throw new FlujoException('El logo debe pesar hasta 1 MB.');
        }
        $bytes = (string) file_get_contents($f->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_WEBP => 'webp'];
        if (! $info || ! isset($tipos[$info[2]])) {
            throw new FlujoException('Formato no permitido: use PNG, JPG o WebP (no SVG: puede contener código).'); // validado por contenido
        }
        [$w, $h] = $info;
        if ($w < 32 || $h < 16 || $w > 4000 || $h > 4000) {
            throw new FlujoException('Dimensiones no permitidas (mínimo 32×16 px, máximo 4000×4000 px).');
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            throw new FlujoException('La imagen está dañada.');
        }
        // Se reescala (máx. 600 px de ancho) y se RE-CODIFICA: elimina metadatos (EXIF/XMP) y cualquier carga incrustada.
        if ($w > 600) {
            $img = imagescale($img, 600) ?: $img;
        }
        imagesavealpha($img, true);
        ob_start();
        $mime = $tipos[$info[2]] === 'jpeg' ? 'image/jpeg' : 'image/png';
        $mime === 'image/jpeg' ? imagejpeg($img, null, 88) : imagepng($img, null, 6);
        $salida = (string) ob_get_clean();
        $dim = [imagesx($img), imagesy($img)];
        imagedestroy($img);
        if (! is_dir($this->dirLogo()) && ! mkdir($this->dirLogo(), 0750, true) && ! is_dir($this->dirLogo())) {
            throw new FlujoException('No se pudo guardar el logo.');
        }
        $nombre = bin2hex(random_bytes(12)).($mime === 'image/jpeg' ? '.jpg' : '.png');
        file_put_contents($this->dirLogo().'/'.$nombre, $salida, LOCK_EX);
        @chmod($this->dirLogo().'/'.$nombre, 0640);
        $actual = $this->datos();
        $antes = $actual['logo']['archivo'] ?? null;
        $actual['logo'] = ['archivo' => $nombre, 'mime' => $mime, 'sha256' => hash('sha256', $salida), 'ancho' => $dim[0], 'alto' => $dim[1]];
        unset($actual['version']);
        $this->persistir($actual, $u);
        if ($antes && preg_match('/^[0-9a-f]{24}\.(png|jpg)$/', $antes)) {
            @unlink($this->dirLogo().'/'.$antes);
        }
        $this->audit->registrar('apariencia.logo_subir', 'config_sitio', 'apariencia', ['mime' => $mime, 'ancho' => $dim[0], 'alto' => $dim[1]]);
    }

    public function quitarLogo(Usuario $u): void
    {
        $actual = $this->datos();
        $antes = $actual['logo']['archivo'] ?? null;
        $actual['logo'] = null;
        unset($actual['version']);
        $this->persistir($actual, $u);
        if ($antes && preg_match('/^[0-9a-f]{24}\.(png|jpg)$/', $antes)) {
            @unlink($this->dirLogo().'/'.$antes);
        }
        $this->audit->registrar('apariencia.logo_quitar', 'config_sitio', 'apariencia');
    }

    /** @return array{bytes:string,mime:string,sha256:string}|null */
    public function logo(): ?array
    {
        $l = $this->datos()['logo'] ?? null;
        if (! $l || ! preg_match('/^[0-9a-f]{24}\.(png|jpg)$/', (string) ($l['archivo'] ?? ''))) {
            return null;
        }
        $b = @file_get_contents($this->dirLogo().'/'.$l['archivo']);

        return $b === false || ! hash_equals((string) $l['sha256'], hash('sha256', $b)) ? null : ['bytes' => $b, 'mime' => $l['mime'], 'sha256' => $l['sha256']];
    }

    public function logoDataUri(): ?string
    {
        $l = $this->logo();

        return $l ? 'data:'.$l['mime'].';base64,'.base64_encode($l['bytes']) : null;
    }

    /** CSS con las variables de color (se sirve desde /marca/estilo.css; sin estilos en línea). */
    public function css(): string
    {
        $d = $this->datos();
        $c = $d['colores'];
        $v = fn ($k) => Contraste::esHex((string) $c[$k]) ? $c[$k] : self::DEFECTO['colores'][$k];

        return ":root{--sgd-primary:{$v('primario')};--sgd-title:{$v('titulo')};--sgd-header-bg:{$v('header_fondo')};--sgd-header-fg:{$v('header_texto')};--sgd-nav-bg:{$v('nav_fondo')};--sgd-nav-fg:{$v('nav_texto')};"
            ."--sgd-footer-bg:{$v('footer_fondo')};--sgd-footer-fg:{$v('footer_texto')};--sgd-footer-link:{$v('footer_enlace')};--sgd-logo-h:".(int) $d['header']['logo_alto_px']."px}\n";
    }
}
