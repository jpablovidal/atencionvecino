<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Lista blanca estricta para HTML enriquecido (TinyMCE, salida LLM). */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    /** Patrón de las imágenes de la biblioteca interna (única fuente de imágenes admitida). */
    public const RUTA_IMAGEN = '#^/app/recursos/[0-9]{1,9}$#';

    public function limpiar(string $html): string
    {
        $limpio = $this->purifier()->purify($html);

        // Solo imágenes de la biblioteca interna: cualquier otra (rutas relativas arbitrarias, etc.) se elimina.
        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            return preg_match('/\ssrc="([^"]*)"/i', $m[0], $s) && preg_match(self::RUTA_IMAGEN, html_entity_decode($s[1])) ? $m[0] : '';
        }, $limpio) ?? '';
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }
        $cache = storage_path('framework/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0750, true);
        }
        $c = HTMLPurifier_Config::createDefault();
        $c->set('Core.Encoding', 'UTF-8');
        $c->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $c->set('Cache.SerializerPath', $cache);
        $c->set('HTML.AllowedElements', ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup', 'small', 'code', 'pre',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot',
            'tr', 'td', 'th', 'blockquote', 'span', 'div', 'a', 'hr', 'img']);
        $c->set('HTML.AllowedAttributes', ['a.href', 'a.title', 'td.colspan', 'td.rowspan', 'th.colspan', 'th.rowspan', '*.style', 'table.border',
            'table.cellpadding', 'table.cellspacing', 'table.width', 'td.width', 'td.height', 'th.width', 'th.height', 'td.align', 'td.valign', 'th.align', 'th.valign',
            'th.scope', 'col.width', 'col.span', 'colgroup.span', 'img.src', 'img.alt', 'img.title', 'img.width', 'img.height', 'ol.start', 'ol.type', 'ul.type']);
        $c->set('CSS.AllowedProperties', ['text-align', 'font-weight', 'font-style', 'text-decoration', 'margin-left', 'padding-left', 'color', 'background-color',
            'font-size', 'font-family', 'line-height', 'text-indent', 'letter-spacing', 'text-transform', 'vertical-align', 'width', 'height', 'float',
            'border', 'border-width', 'border-style', 'border-color', 'border-collapse', 'border-top', 'border-right', 'border-bottom', 'border-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'page-break-after', 'page-break-before', 'page-break-inside']);
        $c->set('CSS.AllowedFonts', ['Arial', 'Helvetica', 'sans-serif', 'Times New Roman', 'Times', 'serif', 'Courier New', 'Courier', 'monospace', 'Georgia', 'Verdana', 'Tahoma', 'Roboto', 'DejaVu Sans']);
        $c->set('CSS.Proprietary', true); // habilita page-break-*; solo pasan las propiedades de AllowedProperties
        $c->set('CSS.MaxImgLength', '1400px');
        $c->set('HTML.MaxImgLength', 1400);
        $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $c->set('URI.DisableExternalResources', true); // las imágenes solo pueden ser rutas relativas (biblioteca interna)
        $c->set('Attr.DefaultImageAlt', '');
        $c->set('HTML.Nofollow', true);
        $c->set('AutoFormat.RemoveEmpty', false);
        $c->set('Attr.AllowedFrameTargets', []);

        return $this->purifier = new HTMLPurifier($c);
    }
}
