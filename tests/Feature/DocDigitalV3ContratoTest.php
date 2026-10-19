<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\Unidad;
use App\Services\ExpedienteService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** El cliente cumple el contrato OpenAPI de DocDigital v3 (docs/interop/docdigital-openapi-3.2.514.json). */
class DocDigitalV3ContratoTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Cache::flush();
        $this->ddConfig();
    }

    private function cliente(array $cfg = []): HttpDocDigitalClient
    {
        return new HttpDocDigitalClient(array_merge(config('sgd.docdigital'), $cfg), 'demo');
    }

    /** @return array<string,mixed> */
    private function spec(): array
    {
        return json_decode((string) file_get_contents(base_path('docs/interop/docdigital-openapi-3.2.514.json')), true);
    }

    public function test_el_cuerpo_del_envio_respeta_el_esquema_de_la_especificacion(): void
    {
        $esq = $this->spec()['components']['schemas']['SolicitudDocumentoRequest'];
        $this->ddFake();
        $this->cliente()->enviar($this->pdfValido(), 'oficio.pdf', ['asunto' => 'Remite decreto', 'destino_codigo' => 'DEMO-SEGPRES', 'destino_entidad_id' => 7002, 'origen_codigo' => 'X', 'numero' => 'Oficio N° 1/2026', 'tipo' => 'Oficio']);
        Http::assertSent(function ($r) use ($esq) {
            $cuerpo = $r->data();
            $this->assertSame([], array_values(array_diff(array_keys($cuerpo), array_keys($esq['properties']))), 'campos que la especificación no define');
            $this->assertSame([], array_values(array_diff($esq['required'], array_keys($cuerpo))), 'campos obligatorios ausentes');
            $this->assertLessThanOrEqual(150, mb_strlen($cuerpo['materia']));
            $this->assertLessThanOrEqual(250, mb_strlen($cuerpo['descripcion']));

            return true;
        });
    }

    public function test_el_envio_devuelve_el_id_de_documento_y_la_solicitud(): void
    {
        $this->ddFake();
        $r = $this->cliente()->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => null, 'tipo' => 'Oficio']);
        $this->assertSame(['id' => '987654', 'simulado' => false, 'solicitud_id' => '555'], $r);
        Http::assertSent(fn ($req) => $req['folio'] === 'S/N'); // folio es obligatorio en la especificación
    }

    public function test_materia_se_recorta_y_el_documento_reservado_se_marca(): void
    {
        $this->ddFake();
        $this->cliente()->enviar($this->pdfValido(), 'o.pdf', ['asunto' => str_repeat('Á', 400), 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio', 'reservado' => true]);
        Http::assertSent(fn ($r) => mb_strlen($r['materia']) === 150 && mb_strlen($r['descripcion']) === 250 && $r['reservado'] === true && $r['es_reservado'] === true);
    }

    public function test_entidad_destino_se_busca_por_nombre_exacto_y_unico(): void
    {
        $this->ddFake([], [], [$this->dd.'/entidades/*' => Http::response($this->ddOk([
            ['entidad_id' => 111, 'entidad_nombre' => 'Contraloría Regional'], ['entidad_id' => 222, 'entidad_nombre' => 'Contraloría General de la República']]), 200)]);
        $c = $this->cliente();
        $c->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_nombre' => 'contraloría regional', 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/documentos/firmado/ingresar') && $r['listado_id_entidades_destinatarias'] === [111]);
        $this->expectException(DocDigitalException::class);
        $this->expectExceptionMessage('entidad destinataria');
        $c->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_nombre' => 'Contraloría', 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio']); // ambiguo
    }

    public function test_entidad_creadora_y_tipo_se_resuelven_desde_la_api_cuando_no_se_configuran(): void
    {
        $this->ddFake();
        $c = $this->cliente(['entidad_id' => null, 'tipo_documento_id' => null]);
        $c->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'oficio']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/documentos/firmado/ingresar') && $r['id_entidad_creadora'] === 5001 && $r['tipo_id'] === 3);
        // tipo sin equivalente: se pide configurarlo
        try {
            $this->cliente(['tipo_documento_id' => null])->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Decreto inexistente']);
            $this->fail('debía fallar');
        } catch (DocDigitalException $e) {
            $this->assertStringContainsString('DOCDIGITAL_TIPO_DOCUMENTO_ID', $e->getMessage());
        }
        // varias entidades en el token: exige indicar cuál
        Cache::flush();
        $this->ddFake([], [], [$this->dd.'/entidades/token' => Http::response($this->ddOk([['entidad_id' => 1, 'entidad_nombre' => 'A'], ['entidad_id' => 2, 'entidad_nombre' => 'B']]), 200)]);
        $this->expectException(DocDigitalException::class);
        $this->expectExceptionMessage('DOCDIGITAL_ENTIDAD_ID');
        $this->cliente(['entidad_id' => null])->enviar($this->pdfValido(), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio']);
    }

    public function test_archivo_mayor_a_20_mb_se_rechaza_antes_de_enviar(): void
    {
        Http::fake();
        $this->expectException(DocDigitalException::class);
        try {
            $this->cliente(['max_bytes' => 50 * 1024 * 1024])->enviar(str_repeat('A', 20 * 1024 * 1024 + 1), 'o.pdf', ['asunto' => 'A', 'destino_codigo' => 'x', 'destino_entidad_id' => 1, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio']);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_acuse_y_devolucion_usan_las_rutas_de_la_especificacion(): void
    {
        $this->ddFake();
        $c = $this->cliente();
        $this->assertSame('acuse-101-tarea-77', $c->acusarRecibo('101')['evidencia']);
        $this->assertSame('devolucion-101-tarea-78', $c->devolver('101', 'Documento mal dirigido')['evidencia']);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/documentos/recibidos/101/acusorecibo') && $r['entidadDestinataria'] === 5001);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/documentos/recibidos/101/devolver') && $r['motivo'] === 'Documento mal dirigido');
        // el id va en la ruta: solo dígitos (sin inyección de rutas) y el motivo respeta 3–255 caracteres
        foreach (['../x', '1/2', '10 1', ''] as $malo) {
            try {
                $c->acusarRecibo($malo);
                $this->fail("id inválido aceptado: $malo");
            } catch (DocDigitalException) {
            }
        }
        $this->expectException(DocDigitalException::class);
        $c->devolver('101', 'no');
    }

    public function test_recibidos_pagina_descarga_y_omite_lo_que_no_se_pudo_bajar(): void
    {
        $this->ddFake([301 => 'Uno', 302 => 'Dos'], [301 => $this->pdfValido('a')], [$this->dd.'/documentos/302/archivo/descargar*' => Http::response('', 404)]);
        $r = $this->cliente()->recibidos();
        $this->assertCount(1, $r);
        $this->assertSame(['301', 'Uno', 'Gobierno Regional de Valparaíso', 7001], [$r[0]['id'], $r[0]['asunto'], $r[0]['origen_nombre'], $r[0]['origen_entidad_id']]);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/documentos/recibidos') && $req['notificado'] === 'false' && $req['pageSize'] === 50);
    }

    public function test_el_origen_recibido_se_asocia_a_la_institucion_por_su_id_de_entidad(): void
    {
        $this->ddFake([401 => 'Oficio del GORE'], [401 => $this->pdfValido('g')]);
        app(ComunicacionService::class)->sincronizarRecibidos();
        $c = ComunicacionOficial::query()->where('direccion', 'entrante')->firstOrFail();
        $this->assertSame('DEMO-GORE-VAL', $c->institucion_codigo);
        $this->assertSame('401', $c->evidencia);
        // sin institución asociada queda el nombre informado por DocDigital, sin inventar un código
        Institucion::query()->where('codigo', 'DEMO-GORE-VAL')->update(['docdigital_entidad_id' => null]);
        $this->ddFake([402 => 'Otro'], [402 => $this->pdfValido('h')]);
        app(ComunicacionService::class)->sincronizarRecibidos();
        $o = ComunicacionOficial::query()->where('evidencia', '402')->firstOrFail();
        $this->assertNull($o->institucion_codigo);
        $this->assertSame('Gobierno Regional de Valparaíso', $o->institucion_nombre);
    }

    public function test_devolver_un_documento_recibido_desde_el_expediente(): void
    {
        $this->ddFake([501 => 'Documento mal dirigido'], [501 => $this->pdfValido('d')]);
        app(ComunicacionService::class)->sincronizarRecibidos();
        $e = Expediente::query()->where('origen', 'docdigital')->firstOrFail();
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        $sec = $this->usuario('secretario');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/devolver", ['motivo' => 'x'])->assertSessionHasErrors('motivo');
        foreach (['funcionario', 'auditor', 'admin'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->post("/app/comunicaciones/{$c->id}/devolver", ['motivo' => 'No corresponde a esta municipalidad'])->assertForbidden();
        }
        $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('Devolver a la entidad de origen');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/devolver", ['motivo' => 'No corresponde a esta municipalidad'])->assertSessionHas('ok');
        $c->refresh();
        $this->assertSame(['devuelto', 'api'], [$c->acuse_estado, $c->acuse_tipo]);
        $this->assertStringContainsString('No corresponde', (string) $c->acuse_evidencia);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/documentos/recibidos/501/devolver') && $r['motivo'] === 'No corresponde a esta municipalidad');
        $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->assertSee('devuelto a la entidad de origen');
        // ya no admite acuse ni una segunda devolución
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('error');
        $this->assertDatabaseHas('audit_log', ['accion' => 'docdigital.devolucion']);
        $this->expectException(FlujoException::class);
        app(ComunicacionService::class)->devolver($c, $sec, 'otra vez');
    }

    public function test_la_devolucion_exige_modo_api(): void
    {
        $this->ddConfig('outbox');
        $unidad = Unidad::query()->where('codigo', 'SECMUN')->first();
        $c = ComunicacionOficial::query()->create(['direccion' => 'entrante', 'institucion_nombre' => 'GORE', 'asunto' => 'x', 'estado' => 'recibida', 'evidencia_tipo' => 'docdigital_id', 'evidencia' => '9',
            'evidencia_en' => now(), 'unidad_id' => $unidad->id, 'acuse_estado' => 'pendiente']);
        $e = app(ExpedienteService::class)->crearDesdeComunicacion($c, $unidad);
        $c->forceFill(['expediente_id' => $e->id])->save();
        $this->expectException(FlujoException::class);
        $this->expectExceptionMessage('outbox');
        app(ComunicacionService::class)->devolver($c, $this->usuario('secretario'), 'No corresponde');
    }

    public function test_busqueda_de_entidades_para_asociar_ids(): void
    {
        $this->ddFake([], [], [$this->dd.'/entidades/*' => Http::response($this->ddOk([['entidad_id' => 321, 'entidad_nombre' => 'Gobierno Regional de Valparaíso', 'entidad_padre_id' => 10]]), 200)]);
        $sec = $this->usuario('secretario');
        $this->comoFuncionario($sec)->get('/app/docdigital/entidades?q=Gobierno')->assertOk()->assertSee('321')->assertSee('Gobierno Regional de Valparaíso');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/entidades/') && $r['nombreEntidad'] === 'Gobierno');
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/docdigital/entidades?q=Gobierno')->assertForbidden();
        $this->comoFuncionario($sec)->get('/app/docdigital/entidades?q=ab')->assertSessionHasErrors('q');
        $this->ddConfig('outbox');
        $this->comoFuncionario($sec)->get('/app/docdigital/entidades?q=Gobierno')->assertOk()->assertSee('no está en modo');
    }
}
