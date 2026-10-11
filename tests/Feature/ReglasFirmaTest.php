<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\DelegacionFirma;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class ReglasFirmaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private DocumentoService $ds;

    private $exp;

    private Usuario $dom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->ds = app(DocumentoService::class);
        $this->dom = $this->usuario('funcionario', 1);
        $this->exp = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->dom);
    }

    private function doc(string $codigo = 'OFICIO'): Documento
    {
        return $this->ds->crear($this->exp, TipoDocumento::query()->where('codigo', $codigo)->first(), Plantilla::query()->first(), 'Doc', $this->dom);
    }

    public function test_flujo_agil_exige_una_firma_y_clasico_dos(): void
    {
        $sec = $this->usuario('secretario');
        $alc = $this->usuario('alcalde');
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc(), 'agil', [], [$sec->id, $alc->id]));
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc(), 'clasico', [], [$sec->id]));
        $this->assertSame('en_flujo', $this->ds->definirFlujo($this->doc(), 'clasico', [], [$sec->id, $alc->id])->estado);
        $this->assertSame('en_flujo', $this->ds->definirFlujo($this->doc(), 'agil', [], [$sec->id])->estado);
    }

    public function test_misma_persona_no_puede_visar_y_firmar(): void
    {
        $sec = $this->usuario('secretario');
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc(), 'agil', [$sec->id], [$sec->id]));
    }

    public function test_cero_o_n_visaciones_y_turnos_estrictos(): void
    {
        $sec = $this->usuario('secretario');
        $v1 = $this->usuario('funcionario', 2);
        $v2 = $this->usuario('funcionario', 4); // Director DOM
        $d = $this->ds->definirFlujo($this->doc(), 'agil', [$v1->id, $v2->id], [$sec->id]);
        $this->expectFlujo(fn () => $this->ds->firmar($d, $sec, '123456')); // firma antes de visar
        $this->expectFlujo(fn () => $this->ds->visar($d, $v2, true, null)); // v2 antes que v1 (fuera de orden)
        $this->ds->visar($d, $v1, true, null);
        $this->expectFlujo(fn () => $this->ds->visar($d, $v1, true, null)); // ya visó
        $this->ds->visar($d, $v2, true, null);
        $this->assertSame('vigente', $this->ds->firmar($d->refresh(), $sec, '123456')->estado);
    }

    public function test_decreto_alcaldicio_exige_alcalde_o_delegado_vigente(): void
    {
        $sec = $this->usuario('secretario');
        $alc = $this->usuario('alcalde');
        $dir = Usuario::query()->where('cargo', 'Director de Obras')->first(); // delegado vigente (seed)
        // la secretaria NO puede ser primera firma de un decreto alcaldicio
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc('DEC_ALC'), 'agil', [], [$sec->id]));
        // el alcalde sí
        $this->assertSame('en_flujo', $this->ds->definirFlujo($this->doc('DEC_ALC'), 'agil', [], [$alc->id])->estado);
        // el delegado con delegación vigente sí
        $this->assertSame('en_flujo', $this->ds->definirFlujo($this->doc('DEC_ALC'), 'agil', [], [$dir->id])->estado);
        // delegación vencida => no
        DelegacionFirma::query()->update(['vigente_hasta' => now()->subDay()->toDateString()]);
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc('DEC_ALC'), 'agil', [], [$dir->id]));
        // delegación revocada => no
        DelegacionFirma::query()->update(['vigente_hasta' => now()->addMonth()->toDateString(), 'activo' => false]);
        $this->expectFlujo(fn () => $this->ds->definirFlujo($this->doc('DEC_ALC'), 'agil', [], [$dir->id]));
        // la delegación vale solo para ese tipo: oficio no la requiere pero decreto SIAPER tampoco
        $this->assertSame('en_flujo', $this->ds->definirFlujo($this->doc('DEC_SIAPER'), 'agil', [], [$sec->id])->estado);
    }

    public function test_solo_el_alcalde_crea_delegaciones(): void
    {
        $payload = ['tipo_documento_id' => 1, 'delegado_id' => 3, 'decreto_referencia' => 'Decreto 5/2026', 'vigente_desde' => '2026-02-01', 'vigente_hasta' => '2026-03-01'];
        foreach (['admin', 'funcionario', 'secretario', 'auditor'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->post('/app/delegaciones', $payload)->assertForbidden();
        }
        $this->comoFuncionario($this->usuario('alcalde'))->post('/app/delegaciones', $payload)->assertRedirect();
        $this->assertSame(2, DelegacionFirma::query()->count());
        $this->assertSame(2, DelegacionFirma::query()->latest('id')->first()->creado_por);
    }

    public function test_no_se_edita_documento_en_flujo_ni_vigente(): void
    {
        $sec = $this->usuario('secretario');
        $d = $this->ds->definirFlujo($this->doc(), 'agil', [], [$sec->id]);
        $this->expectFlujo(fn () => $this->ds->guardar($d, 'x', '<p>cambio</p>', [], $this->dom));
        $d = $this->ds->firmar($d, $sec, '123456');
        $this->expectFlujo(fn () => $this->ds->guardar($d, 'x', '<p>cambio</p>', [], $this->dom));
        $this->comoFuncionario($this->dom)->post("/app/documentos/{$d->id}", ['titulo' => 'x', 'html' => '<p>x</p>', 'nivel_acceso' => 'publico'])->assertSessionHas('error');
    }

    public function test_otp_fallido_no_avanza_el_flujo_y_no_se_almacena(): void
    {
        $sec = $this->usuario('secretario');
        $d = $this->ds->definirFlujo($this->doc(), 'agil', [], [$sec->id]);
        $this->expectFlujo(fn () => $this->ds->firmar($d, $sec, '000000'));
        $d->refresh();
        $this->assertSame('en_flujo', $d->estado);
        $this->assertSame('pendiente', $d->pasos->first()->estado);
        $this->assertNull($d->numero);
        // el OTP no aparece en ninguna parte de la bitácora ni de la BD
        $this->ds->firmar($d, $sec, '735291');
        $volcado = json_encode(DB::table('audit_log')->get()).json_encode(DB::table('documento_pasos')->get());
        $this->assertStringNotContainsString('735291', $volcado);
        $this->assertStringNotContainsStringIgnoringCase('"otp"', $volcado);
    }

    public function test_rechazo_vuelve_a_borrador_y_finalizar_es_idempotente(): void
    {
        $sec = $this->usuario('secretario');
        $vis = $this->usuario('funcionario', 2);
        $d = $this->ds->definirFlujo($this->doc(), 'agil', [$vis->id], [$sec->id]);
        $this->ds->visar($d, $vis, false, 'corregir');
        $this->assertSame('borrador', $d->refresh()->estado);
        $d = $this->ds->definirFlujo($d, 'agil', [], [$sec->id]);
        $d = $this->ds->firmar($d, $sec, '123456');
        $n = $d->numero;
        $this->assertSame($n, $this->ds->finalizar($d)->numero); // reintento: mismo número
        $this->assertSame($n, $this->ds->finalizar($d)->numero);
        $this->assertSame(1, DB::table('correlativos')->value('ultimo'));
    }

    public function test_versiones_inmutables_y_cada_version_tiene_hashes(): void
    {
        $d = $this->doc();
        $d = $this->ds->guardar($d, 'T', '<p>uno</p><script>alert(1)</script>', [], $this->dom);
        $d = $this->ds->guardar($d, 'T', '<p>dos</p>', [], $this->dom);
        $this->assertSame(3, $d->versiones()->count());
        $v = DocumentoVersion::query()->first();
        $this->assertSame(64, strlen($v->html_sha256));
        $this->assertSame(64, strlen($v->pdf_sha256));
        $this->assertStringNotContainsString('script', DocumentoVersion::query()->orderByDesc('id')->skip(1)->first()->html);
        $this->expectException(\LogicException::class);
        $v->update(['html' => 'alterado']);
    }

    public function test_version_inmutable_a_nivel_de_bd_por_trigger(): void
    {
        $this->doc();
        $this->expectException(QueryException::class);
        DB::table('documento_versiones')->update(['html' => 'x']);
    }

    private function expectFlujo(callable $fn): void
    {
        try {
            $fn();
        } catch (FlujoException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Se esperaba FlujoException');
    }
}
