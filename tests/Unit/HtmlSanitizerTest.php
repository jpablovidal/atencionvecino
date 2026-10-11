<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public static function ataques(): array
    {
        return [
            ['<p>hola</p><script>alert(1)</script>', 'script'],
            ['<img src=x onerror=alert(1)>', 'onerror'],
            ['<a href="javascript:alert(1)">x</a>', 'javascript'],
            ['<iframe src="https://evil.cl"></iframe>', 'iframe'],
            ['<p style="background:url(javascript:alert(1))">x</p>', 'javascript'],
            ['<svg onload=alert(1)></svg>', 'onload'],
            ['<form action="https://evil.cl"><input name=x></form>', 'form'],
            ['<object data="x"></object><embed src="x">', 'embed'],
            ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
            ['<link rel=stylesheet href="https://evil.cl/x.css"><style>@import url(x)</style>', 'stylesheet'],
        ];
    }

    #[DataProvider('ataques')]
    public function test_elimina_vectores_xss(string $entrada, string $prohibido): void
    {
        $this->assertStringNotContainsStringIgnoringCase($prohibido, (new HtmlSanitizer)->limpiar($entrada));
    }

    public function test_conserva_formato_permitido(): void
    {
        $out = (new HtmlSanitizer)->limpiar('<h2>Título</h2><p style="text-align:center"><strong>negrita</strong> <em>it</em></p><ul><li>a</li></ul><table><tr><td>1</td></tr></table><a href="https://santodomingo.cl">ok</a>');
        foreach (['<h2>', '<strong>', 'text-align:center', '<li>', '<table>', 'href="https://santodomingo.cl"'] as $x) {
            $this->assertStringContainsString($x, $out);
        }
    }
}
