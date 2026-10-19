<?php

namespace Tests\Feature;

use App\Models\ConfigSitio;
use App\Models\DocDigitalEvidencia;
use App\Services\Interop\DocDigital\EvidenciaService;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Registro de evidencias (JSON de cada llamada) y ajustes según el manual de integración API MW v2. */
class DocDigitalEvidenciaTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->ddConfig('demo', ['sgd.docdigital.client_id' => 'uapi_demo', 'sgd.docdigital.client_secret' => 'SECRETO-DEMO-XYZ', 'sgd.docdigital.api_token' => null]);
        Cache::flush();
        EvidenciaService::olvidar();
    }

    private function cliente(string $amb = 'demo'): HttpDocDigitalClient
    {
        return new HttpDocDigitalClient(array_merge(config('sgd.docdigital'), ['timeout' => 5]), $amb);
    }

    public function test_inactivo_no_registra_nada(): void
    {
        $this->ddFake();
        $this->cliente()->entidadesDelToken();
        $this->assertSame(0, DocDigitalEvidencia::query()->count());
    }

    public function test_activo_registra_json_sin_secretos_ni_archivos(): void
    {
        $this->ddFake();
        EvidenciaService::activar(24);
        $c = $this->cliente();
        $c->enviar('%PDF-1.4 contenido', 'oficio 1.pdf', ['asunto' => 'Asunto', 'numero' => '10', 'destino_entidad_id' => 7001, 'anexos' => [['nombre' => 'a.txt', 'bytes' => str_repeat('A', 500)]]] + ['institucion_destino' => null]);
        $filas = DocDigitalEvidencia::query()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $filas->count());
        $tok = $filas->firstWhere('ruta', '/oauth/token');
        $this->assertNotNull($tok);
        $this->assertStringNotContainsString('TOK-ABC', (string) $tok->respuesta);
        $this->assertStringContainsString('[oculto]', (string) $tok->respuesta);
        $ing = $filas->firstWhere('ruta', '/documentos/firmado/ingresar');
        $this->assertSame('POST', $ing->metodo);
        $this->assertSame(200, $ing->http_status);
        $this->assertStringContainsString('base64 omitido', (string) $ing->solicitud);
        $this->assertStringContainsString('sha256', (string) $ing->solicitud);
        $this->assertStringNotContainsString(base64_encode('%PDF-1.4 contenido'), (string) $ing->solicitud);
        $this->assertStringContainsString('"materia": "Asunto"', (string) $ing->solicitud);
        $todo = json_encode(DocDigitalEvidencia::query()->get()->toArray());
        $this->assertStringNotContainsString('SECRETO-DEMO-XYZ', $todo);
        $this->assertStringNotContainsString(base64_encode('uapi_demo:SECRETO-DEMO-XYZ'), $todo);
        $this->assertStringNotContainsString('Authorization', $todo);
    }

    public function test_registra_el_error_http_y_la_activacion_vence(): void
    {
        $this->ddFake([], [], [$this->dd.'/entidades/token' => Http::response('No autorizado', 403)]);
        EvidenciaService::activar(24);
        try {
            $this->cliente()->entidadesDelToken();
        } catch (\Throwable) {
        }
        $e = DocDigitalEvidencia::query()->where('ruta', '/entidades/token')->first();
        $this->assertSame(403, $e->http_status);
        $this->assertSame('No autorizado', $e->respuesta);
        ConfigSitio::query()->where('clave', EvidenciaService::CLAVE)->update(['valor' => now()->subMinute()->format('Y-m-d H:i:s')]);
        EvidenciaService::olvidar();
        $this->assertFalse(EvidenciaService::activa());
    }

    public function test_panel_activar_descargar_y_borrar_solo_con_permiso(): void
    {
        $sec = $this->usuario('secretario');
        $this->ddFake();
        $this->comoFuncionario($sec)->post('/app/docdigital/evidencia', ['accion' => 'activar'])->assertRedirect();
        EvidenciaService::olvidar();
        $this->assertTrue(EvidenciaService::activa());
        $this->cliente()->tiposDocumento();
        $this->comoFuncionario($sec)->get('/app/docdigital')->assertOk()->assertSee('Evidencias de las llamadas')->assertSee('/tipos/documentos/');
        $r = $this->comoFuncionario($sec)->get('/app/docdigital/evidencia/descargar?formato=json')->assertOk();
        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $this->assertNotEmpty(json_decode($r->getContent(), true)['llamadas']);
        $this->comoFuncionario($sec)->get('/app/docdigital/evidencia/descargar?formato=zip')->assertOk();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/docdigital/evidencia', ['accion' => 'borrar'])->assertForbidden();
        $this->comoFuncionario($sec)->post('/app/docdigital/evidencia', ['accion' => 'borrar'])->assertRedirect();
        $this->assertSame(0, DocDigitalEvidencia::query()->count());
    }

    public function test_entidad_destinataria_va_en_la_consulta_y_en_el_cuerpo(): void
    {
        $this->ddFake();
        $this->cliente()->acusarRecibo('123');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/documentos/recibidos/123/acusorecibo?entidadDestinataria=5001') && ($r->data()['entidadDestinataria'] ?? null) === 5001);
    }

    public function test_nombres_de_archivo_segun_el_manual(): void
    {
        $this->assertSame('Oficio N°12 (final).pdf', HttpDocDigitalClient::nombreArchivo('Oficio N°12 (final).pdf'));
        $this->assertSame('a_b_c.pdf', HttpDocDigitalClient::nombreArchivo('a/b\\c.pdf'));
        $this->assertSame('ab_', HttpDocDigitalClient::nombreArchivo('ab'));
        $largo = HttpDocDigitalClient::nombreArchivo(str_repeat('ñ', 300).'.pdf');
        $this->assertSame(150, mb_strlen($largo));
        $this->assertStringEndsWith('.pdf', $largo);
    }

    public function test_produccion_usa_sus_propias_credenciales_y_no_mezcla(): void
    {
        $base = array_merge(config('sgd.docdigital'), ['timeout' => 5]);
        $c = new HttpDocDigitalClient(array_merge($base, ['prod_client_id' => 'uapi_prod', 'prod_client_secret' => 'SEC-PROD', 'prod_entidad_id' => 99]), 'prod');
        $this->assertTrue($c->tieneCredenciales());
        $r = new \ReflectionProperty($c, 'cfg');
        $cfg = $r->getValue($c);
        $this->assertSame('uapi_prod', $cfg['client_id']);
        $this->assertSame('SEC-PROD', $cfg['client_secret']);
        $this->assertSame(99, $cfg['entidad_id']);
        $mix = (new \ReflectionProperty($c2 = new HttpDocDigitalClient(array_merge($base, ['prod_client_id' => 'uapi_prod']), 'prod'), 'cfg'))->getValue($c2);
        $this->assertNull($mix['client_secret']);
        $demo = (new \ReflectionProperty($c3 = new HttpDocDigitalClient(array_merge($base, ['prod_client_id' => 'uapi_prod', 'prod_client_secret' => 'SEC-PROD']), 'demo'), 'cfg'))->getValue($c3);
        $this->assertSame('uapi_demo', $demo['client_id']);
    }
}
