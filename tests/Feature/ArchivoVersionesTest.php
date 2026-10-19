<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\ArchivoVersion;
use App\Models\DocumentoVersionEvento;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Services\AccesoRestringidoService;
use App\Services\ArchivoVersionService;
use App\Services\DocumentoHistorialService;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class ArchivoVersionesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Expediente $e;

    private Archivo $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario'));
        $this->a = Archivo::query()->where('expediente_id', $this->e->id)->where('ambito', 'anexo')->firstOrFail();
    }

    private function svc(): ArchivoVersionService
    {
        return app(ArchivoVersionService::class);
    }

    public function test_un_cambio_crea_una_version_nueva_y_conserva_la_anterior(): void
    {
        $u = $this->usuario('funcionario');
        $original = app(FileVault::class)->get($this->a->ruta, $this->a->sha256);
        $v2 = $this->svc()->nuevaVersion($this->a, $this->archivo('corregido.pdf', $this->pdfValido('% montos corregidos')), $u, 'Se corrigió el monto del presupuesto');
        $this->assertSame(2, $v2->numero);
        $this->assertSame($u->id, $v2->creado_por);
        $a = $this->a->fresh();
        $this->assertSame(2, (int) $a->version);
        $this->assertSame($v2->sha256, $a->sha256);
        // la versión 1 existe, íntegra y legible
        $v1 = ArchivoVersion::query()->where('archivo_id', $a->id)->where('numero', 1)->firstOrFail();
        $this->assertSame($original, $this->svc()->contenido($v1));
        $this->assertNotNull($v1->autor_nombre, 'la versión original registra quién la subió');
        $this->assertStringContainsString('Se corrigió', (string) $v2->motivo);
        $this->assertNotEmpty($v2->resumen()['texto_resumen']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'archivo.version']);
    }

    public function test_exige_motivo_y_rechaza_archivo_identico_o_invalido(): void
    {
        $u = $this->usuario('funcionario');
        foreach ([fn () => $this->svc()->nuevaVersion($this->a, $this->archivo('x.pdf', $this->pdfValido('% v2')), $u, 'ok'),
            fn () => $this->svc()->nuevaVersion($this->a, $this->archivo('igual.pdf', app(FileVault::class)->get($this->a->ruta, $this->a->sha256)), $u, 'Sin cambios reales aquí'),
            fn () => $this->svc()->nuevaVersion($this->a, $this->archivo('mal.pdf', "MZ\x90 malware"), $u, 'Intento con ejecutable')] as $i => $f) {
            try {
                $f();
                $this->fail("caso $i debió fallar");
            } catch (FlujoException) {
            }
        }
        $this->assertSame(1, (int) $this->a->fresh()->version);
    }

    public function test_diferencias_de_texto_y_pdf(): void
    {
        $r = $this->svc()->diferencias("a\nb\nc", 'text/plain', 'x.txt', 5, "a\nc\nd\ne", 'text/plain', 'x.txt', 7);
        $this->assertSame(2, $r['texto']['agregadas']);
        $this->assertSame(1, $r['texto']['quitadas']);
        $this->assertSame('lineas', $r['contenido_comparado']);
        $r2 = $this->svc()->diferencias('x', 'application/zip', 'a.docx', 1, 'xy', 'application/zip', 'a.docx', 2);
        $this->assertSame('no', $r2['contenido_comparado']);
        $this->assertStringContainsString('no se compara', $r2['texto_resumen']);
    }

    public function test_el_visor_enmarca_solo_desde_el_mismo_origen_y_el_resto_no(): void
    {
        $u = $this->usuario('funcionario');
        $r = $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/ver");
        $r->assertOk();
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('inline', (string) $r->headers->get('Content-Disposition'));
        $this->assertNull($r->headers->get('X-SGD-Embebible'));
        $p = $this->comoFuncionario($u)->get('/app');
        $this->assertSame('DENY', $p->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $p->headers->get('Content-Security-Policy'));
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertOk(); // la descarga sigue siendo adjunto
    }

    public function test_la_pagina_del_expediente_trae_el_modal_y_los_enlaces_de_versiones(): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/expedientes/'.$this->e->id)->assertOk()
            ->assertSee('id="sgd-visor"', false)->assertSee('data-visor', false)->assertSee('Abrir en una pestaña nueva')->assertSee('Versiones');
    }

    public function test_pantalla_de_versiones_y_subida_por_http(): void
    {
        $u = $this->usuario('funcionario');
        $c = $this->comoFuncionario($u);
        $c->post("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/versiones", ['archivo' => $this->archivo('v2.pdf', $this->pdfValido('% cambio')), 'motivo' => 'Actualización solicitada por el vecino'])->assertRedirect();
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/versiones")->assertOk()->assertSee('Versión 2')->assertSee('Versión 1')->assertSee('Actualización solicitada');
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/ver/1")->assertOk();
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/versiones/1/descargar")->assertOk();
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/ver/9")->assertNotFound();
    }

    public function test_versiones_de_un_archivo_restringido_exigen_autorizacion(): void
    {
        $d = $this->usuario('funcionario');
        app(AccesoRestringidoService::class)->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante', $this->a);
        $this->comoFuncionario($this->usuario('funcionario', 2))->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/ver")->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/versiones")->assertForbidden();
        $this->comoFuncionario($d)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}/ver")->assertOk();
    }

    public function test_historial_de_documento_registra_quien_edito_viso_y_firmo_por_version(): void
    {
        $ds = app(DocumentoService::class);
        $autor = $this->usuario('funcionario');
        $sec = $this->usuario('secretario');
        $tipo = TipoDocumento::query()->where('codigo', 'OFICIO')->first();
        $doc = $ds->crear($this->e, $tipo, null, 'Oficio de prueba', $autor, [], '<p>Primer párrafo</p><p>Segundo párrafo</p>');
        $doc = $ds->guardar($doc, 'Oficio de prueba', '<p>Primer párrafo</p><p>Segundo párrafo corregido</p><p>Tercero</p>', [], $autor);
        $vis = $this->usuario('alcalde');
        $ds->definirFlujo($doc, 'agil', [$vis->id], [$sec->id]);
        $ds->visar($doc->refresh(), $vis, true, 'Conforme');
        $doc = $ds->firmar($doc->refresh(), $sec, '111222');
        $this->assertSame('vigente', $doc->estado);
        $h = app(DocumentoHistorialService::class)->historial($doc);
        $this->assertCount(2, $h);
        $nueva = $h[0];
        $this->assertSame(2, $nueva['v']->version);
        $this->assertStringContainsString('agregado', $nueva['cambios']['resumen']);
        $tipos = $nueva['eventos']->pluck('evento')->all();
        foreach (['editado', 'visado', 'firmado'] as $ev) {
            $this->assertContains($ev, $tipos);
        }
        $this->assertTrue($nueva['eventos']->contains(fn ($e) => $e->evento === 'firmado' && $e->usuario_id === $sec->id));
        $this->assertSame(1, DocumentoVersionEvento::query()->where('documento_id', $doc->id)->where('version', 1)->count(), 'la v1 solo registra su edición');
        $this->comoFuncionario($autor)->get("/app/expedientes/{$this->e->id}/documentos/{$doc->id}/historial")->assertOk()->assertSee('Firmó')->assertSee('Visó')->assertSee('Versión 2');
        $this->expectException(\LogicException::class);
        DocumentoVersionEvento::query()->first()->delete();
    }
}
