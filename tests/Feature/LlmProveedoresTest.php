<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Services\Admin\ApiConfigService;
use App\Services\LlmAsistente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Selección del proveedor de IA (Gemini/AI Studio, Qwen y otros gratuitos). */
class LlmProveedoresTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private function usar(string $prov, array $extra = []): void
    {
        config(['sgd.llm' => array_merge(config('sgd.llm'), ['enabled' => true, 'api_key' => 'k-secreta', 'provider' => $prov, 'model' => null], $extra)]);
        Http::swap(new Factory);
    }

    public function test_gemini_usa_la_clave_en_cabecera_y_el_formato_de_google(): void
    {
        $this->usar('gemini');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'pensando', 'thought' => true], ['text' => 'Borrador Gemini']]]]]], 200)]);
        $r = app(LlmAsistente::class)->generar('Redacta un oficio', 'RUN 11111111-1');
        $this->assertStringContainsString('Borrador Gemini', $r['html']);
        $this->assertStringNotContainsString('pensando', $r['html']);
        Http::assertSent(function ($q) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent', $q->url());
            $this->assertSame('k-secreta', $q->header('x-goog-api-key')[0]);
            $this->assertStringNotContainsString('k-secreta', $q->url());
            $this->assertStringNotContainsString('11111111', json_encode($q->data()));

            return isset($q['systemInstruction']) && isset($q['contents'][0]['parts'][0]['text']);
        });
    }

    public function test_qwen_y_otros_openai_usan_bearer_y_chat_completions(): void
    {
        foreach (['qwen' => 'dashscope-intl.aliyuncs.com', 'groq' => 'api.groq.com', 'openrouter' => 'openrouter.ai', 'mistral' => 'api.mistral.ai'] as $prov => $host) {
            $this->usar($prov, ['model' => 'mi-modelo:free']);
            Http::fake(["$host/*" => Http::response(['choices' => [['message' => ['content' => "Texto $prov"]]]], 200)]);
            $this->assertStringContainsString("Texto $prov", app(LlmAsistente::class)->generar('Redacta', '')['html']);
            Http::assertSent(fn ($q) => $q->hasHeader('Authorization', 'Bearer k-secreta') && $q['model'] === 'mi-modelo:free' && $q['messages'][0]['role'] === 'system' && str_contains($q->url(), $host));
        }
    }

    public function test_proveedor_otro_exige_https_con_dominio(): void
    {
        foreach (['http://api.ejemplo.cl/v1/chat/completions', 'https://127.0.0.1/v1', 'https://localhost/v1', '', 'https://10.0.0.5/v1'] as $u) {
            $this->usar('otro', ['url_otro' => $u, 'model' => 'm']);
            Http::fake();
            try {
                app(LlmAsistente::class)->generar('x', '');
                $this->fail("debió rechazar «$u»");
            } catch (\RuntimeException) {
                Http::assertNothingSent();
            }
        }
        $this->usar('otro', ['url_otro' => 'https://api.ejemplo.cl/v1/chat/completions', 'model' => 'm']);
        Http::fake(['api.ejemplo.cl/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
        $this->assertStringContainsString('ok', app(LlmAsistente::class)->generar('x', '')['html']);
    }

    public function test_el_modelo_no_admite_comandos_ni_direcciones(): void
    {
        $this->sembrar();
        $admin = $this->usuario('admin');
        $this->actingAs($admin);
        $this->expectException(FlujoException::class);
        app(ApiConfigService::class)->guardar('llm', ['enabled' => '1', 'provider' => 'gemini', 'model' => 'curl "https://x.com/v1beta/models/gemini:generateContent" -H "x: y"'], true, [], $admin);
    }

    public function test_proveedor_desconocido_y_error_sin_filtrar_la_clave(): void
    {
        $this->usar('inventado');
        $this->expectException(\RuntimeException::class);
        app(LlmAsistente::class)->generar('x', '');
    }

    public function test_un_error_del_proveedor_no_filtra_la_clave(): void
    {
        $this->usar('gemini');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key k-secreta no válida']], 400)]);
        try {
            app(LlmAsistente::class)->generar('x', '');
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('k-secreta', $e->getMessage());
        }
    }

    public function test_el_panel_ofrece_los_proveedores_y_valida(): void
    {
        $this->sembrar();
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->get('/app/admin/api/llm')->assertOk()->assertSee('Google AI Studio (Gemini)', false)->assertSee('Alibaba Qwen', false)->assertSee('OpenRouter', false);
        $this->usar('qwen');
        Http::fake(['dashscope-intl.aliyuncs.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]], 200)]);
        $filas = collect(app(ApiConfigService::class)->probar('llm'));
        $this->assertTrue($filas->every(fn ($f) => $f[1]), json_encode($filas));
        $this->assertStringNotContainsString('k-secreta', json_encode($filas));
    }
}
