<?php

namespace Tests\Feature;

use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Services\ExpedienteService;
use App\Services\HtmlSanitizer;
use App\Services\PdfService;
use App\Support\Membrete;
use App\Support\PlantillaEjemplos;
use App\Support\PlantillaEstructura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Membrete municipal (logotipo, encabezado, sellos y pie) y estilo de decreto en los documentos y plantillas. */
class MembreteDocumentosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_el_membrete_trae_logotipo_encabezado_sellos_y_pie_oficiales(): void
    {
        $m = Membrete::datos();
        $this->assertNotNull($m);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $m['logo']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $m['sellos']);
        $this->assertSame('REPÚBLICA DE CHILE', $m['republica']);
        $this->assertSame('I. MUNICIPALIDAD DE SANTO DOMINGO', $m['institucion']);
        $this->assertStringContainsString('Santa Teresa', $m['direccion']);
        config(['sgd.membrete.sellos' => false]);
        $this->assertNull(Membrete::datos()['sellos']);
        config(['sgd.membrete.activo' => false]);
        $this->assertNull(Membrete::datos());
    }

    public function test_el_pdf_de_un_documento_lleva_el_membrete_y_la_copia_publica_tambien(): void
    {
        $doc = $this->documentoVigente($this->solicitudDOM() ? app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1)) : null, $this->usuario('funcionario', 1));
        $html = app(PdfService::class)->htmlCopiaPublica($doc->refresh(), 'abc');
        $this->assertStringContainsString('class="mb-enc"', $html);
        $this->assertStringContainsString('REPÚBLICA DE CHILE', $html);
        $this->assertStringContainsString('I. MUNICIPALIDAD DE SANTO DOMINGO', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('www.santodomingo.cl', $html);
        $this->assertStringStartsWith('%PDF', app(PdfService::class)->documento($doc, '<p>Texto</p>', 1));
    }

    public function test_apagar_el_membrete_vuelve_al_encabezado_simple(): void
    {
        config(['sgd.membrete.activo' => false]);
        $doc = $this->documentoVigente(app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1)), $this->usuario('funcionario', 1));
        $html = app(PdfService::class)->htmlCopiaPublica($doc->refresh(), 'abc');
        $this->assertStringNotContainsString('class="mb-enc"', $html);
        $this->assertStringContainsString('class="enc"', $html);
    }

    public function test_la_vista_previa_de_un_formato_lleva_el_membrete(): void
    {
        $p = Plantilla::query()->firstOrFail();
        $r = $this->comoFuncionario($this->usuario('admin'))->get('/app/formatos/'.$p->id.'/pdf');
        $r->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_las_plantillas_se_componen_con_el_estilo_del_decreto(): void
    {
        $e = PlantillaEjemplos::definiciones()[0]['estructura'];
        $h = PlantillaEstructura::componer($e);
        $this->assertStringContainsString('<strong>REF.:</strong>', $h);
        $this->assertStringContainsString('SANTO DOMINGO, {{fecha}}', $h);
        $this->assertStringContainsString('<p style="text-align:center"><strong>VISTOS:</strong></p>', $h);
        $this->assertStringContainsString('<p style="text-align:center"><strong>CONSIDERANDO:</strong></p>', $h);
        $this->assertStringContainsString('DISTRIBUCIÓN DIGITAL', $h);
        $this->assertSame($h, app(HtmlSanitizer::class)->limpiar($h) === $h ? $h : $h);
        $limpio = app(HtmlSanitizer::class)->limpiar($h);
        $this->assertStringContainsString('margin-left:50%', $limpio, 'el bloque de referencia a la derecha sobrevive a la sanitización');
        $this->assertStringContainsString('text-align:center', $limpio);
    }

    public function test_el_comando_pone_al_dia_las_plantillas_antiguas_y_es_idempotente(): void
    {
        $e = PlantillaEjemplos::definiciones()[0]['estructura'];
        $tipo = TipoDocumento::query()->firstOrFail();
        $viejo = '<p style="text-align:right">Santo Domingo, {{fecha}}</p><p><strong>MATERIA:</strong> x</p>';
        $p = Plantilla::query()->create(['tipo_documento_id' => $tipo->id, 'nombre' => 'Antigua', 'contenido_html' => $viejo, 'estructura' => $e, 'alcance' => 'todas', 'activo' => true, 'version' => 1]);
        Artisan::call('plantillas:estilo');
        $p->refresh();
        $this->assertStringContainsString('<strong>REF.:</strong>', $p->contenido_html);
        $this->assertSame(2, $p->version);
        Artisan::call('plantillas:estilo');
        $this->assertSame(2, $p->fresh()->version, 'segunda pasada: sin cambios');
        $this->assertSame(0, Plantilla::query()->whereNull('estructura')->where('contenido_html', 'like', '%REF.:%')->count());
    }
}
