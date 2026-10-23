<?php

namespace Tests\Feature;

use App\Models\DocumentoPaso;
use App\Models\TipoDocumento;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** La bandeja de quien gestiona expedientes separa lo «por firmar» de lo «por visar». */
class BandejaPorFirmarVisarTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function doc(string $titulo)
    {
        $exp = $this->solicitudDOM();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($exp, $this->usuario('funcionario'));
        $tipo = TipoDocumento::query()->where('codigo', 'OFICIO')->first();

        return app(DocumentoService::class)->crear($e, $tipo, null, $titulo, $this->usuario('funcionario'));
    }

    public function test_separa_por_firmar_y_por_visar_con_contadores(): void
    {
        $u = $this->usuario('secretario');
        $a = $this->doc('Oficio para visar');
        $b = $this->doc('Oficio para firmar');
        foreach ([[$a, 'visacion'], [$b, 'firma']] as [$d, $tipo]) {
            $d->forceFill(['estado' => 'en_flujo'])->save();
            DocumentoPaso::query()->create(['documento_id' => $d->id, 'orden' => 1, 'tipo' => $tipo, 'usuario_id' => $u->id, 'estado' => 'pendiente']);
        }
        $r = $this->comoFuncionario($u)->get('/app')->assertOk();
        $r->assertSee('Por firmar')->assertSee('Por visar')->assertSee('Oficio para visar')->assertSee('Oficio para firmar');
        $html = $r->getContent();
        $iF = strpos($html, 'id="b-para-firmar"');
        $iV = strpos($html, 'id="b-para-visar"');
        $this->assertNotFalse($iF);
        $this->assertNotFalse($iV);
        $this->assertLessThan($iV, strpos($html, 'Oficio para firmar'), 'lo por firmar va en su columna, antes de la de visar');
        $this->assertGreaterThan($iV, strpos($html, 'Oficio para visar'), 'lo por visar va en su columna');
        $this->assertLessThan($iV, $iF);
    }

    public function test_la_pagina_de_firmas_tiene_los_accesos_a_cada_lista(): void
    {
        $this->comoFuncionario($this->usuario('secretario'))->get('/app/firmas')->assertOk()->assertSee('href="#para-firmar"', false)->assertSee('href="#para-visar"', false);
    }
}
