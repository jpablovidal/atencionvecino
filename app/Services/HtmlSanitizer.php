<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Lista blanca estricta para HTML enriquecido (TinyMCE, salida LLM). */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function limpiar(string $html): string
    {
        return $this->purifier()->purify($html);
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
        $c->set('HTML.AllowedElements', ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4',
            'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote', 'span', 'div', 'a', 'hr']);
        $c->set('HTML.AllowedAttributes', ['a.href', 'a.title', 'td.colspan', 'td.rowspan', 'th.colspan', 'th.rowspan', '*.style', 'table.border']);
        $c->set('CSS.AllowedProperties', ['text-align', 'font-weight', 'font-style', 'text-decoration', 'margin-left', 'padding-left']);
        $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $c->set('HTML.Nofollow', true);
        $c->set('AutoFormat.RemoveEmpty', false);
        $c->set('Attr.AllowedFrameTargets', []);

        return $this->purifier = new HTMLPurifier($c);
    }
}
