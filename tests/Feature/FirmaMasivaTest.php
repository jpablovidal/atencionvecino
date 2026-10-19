<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\FirmaMasivaService;
use App\Services\PagoConfigService;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class FirmaMasivaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Usuario $dom;

    private Usuario $sec;

    private Expediente $e1;

    private Expediente $e2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->dom = $this->usuario('funcionario', 1);
        $this->sec = $this->usuario('secretario');
        $this->e1 = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->dom);
        $s2 = app(SolicitudService::class)->ingresar(['run' => '20000002-1', 'nombres' => 'Otra', 'apellidos' => 'Vecina'], Tramite::query()->with('anexos')->findOrFail(1), 'o@example.com', 'Otro permiso de edificación.', 'correo',
            [1 => $this->archivo('p2.pdf', $this->pdfValido('p2')), 2 => $this->archivo('d2.pdf', $this->pdfValido('d2'))], []);
        $this->e2 = app(ExpedienteService::class)->crearDesdeSolicitud($s2, $this->dom);
    }

    private function svc(): FirmaMasivaService
    {
        return app(FirmaMasivaService::class);
    }

    /** Oficio con flujo ágil de una firma (de la secretaria), pendiente. */
    private function oficio(Expediente $e, string $titulo): Documento
    {
        $ds = app(DocumentoService::class);
        $doc = $ds->crear($e, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, $titulo, $this->dom);

        return $ds->definirFlujo($doc, 'agil', [], [$this->sec->id]);
    }

    private function falla(callable $fn, string $msg): void
    {
        try {
            $fn();
            $this->fail('debía rechazarse');
        } catch (FlujoException $e) {
            $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
        }
    }

    private function ids(Usuario $u, string $tipo = 'firma'): array
    {
        return $this->svc()->pendientes($u, $tipo)->pluck('paso.id')->all();
    }

    public function test_solo_se_listan_los_pasos_en_los_que_ya_le_toca(): void
    {
        $a = $this->oficio($this->e1, 'Oficio A');
        $this->oficio($this->e2, 'Oficio B');
        $ds = app(DocumentoService::class);
        $c = $ds->crear($this->e1, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, 'Oficio con visación previa', $this->dom);
        $ds->definirFlujo($c, 'agil', [$this->usuario('alcalde')->id], [$this->sec->id]); // la firma espera a la visación del alcalde
        $this->assertCount(2, $this->ids($this->sec), 'el tercero aún no es su turno');
        $this->assertSame([], $this->ids($this->dom));
        $this->assertSame($a->id, $this->svc()->pendientes($this->sec, 'firma')->first()['documento']->id);
    }

    public function test_firma_los_seleccionados_y_deja_los_demas(): void
    {
        $this->oficio($this->e1, 'Oficio A');
        $this->oficio($this->e1, 'Oficio B');
        $this->oficio($this->e2, 'Oficio C');
        $ids = $this->ids($this->sec);
        $res = $this->svc()->firmar($this->sec, [$ids[0], $ids[2]], false, 'avanzada', '111222', true);
        $this->assertSame([true, true], array_values(array_column($res, 'ok')));
        $this->assertSame(2, Documento::query()->where('estado', 'vigente')->count());
        $this->assertSame([$ids[1]], $this->ids($this->sec), 'el no seleccionado sigue pendiente');
        $n = Documento::query()->where('estado', 'vigente')->pluck('numero')->sort()->values()->all();
        $this->assertSame([1, 2], $n, 'cada documento recibe su correlativo');
    }

    public function test_todos_los_pendientes_respeta_el_maximo_del_lote(): void
    {
        foreach (['A', 'B', 'C'] as $t) {
            $this->oficio($this->e1, "Oficio $t");
        }
        config(['sgd.firma_lote_max' => 2]);
        $res = $this->svc()->firmar($this->sec, [], true, 'avanzada', '111222', true);
        $this->assertCount(2, $res);
        $this->assertCount(1, $this->ids($this->sec), 'queda un tramo por repetir');
        $res = $this->svc()->firmar($this->sec, [], true, 'avanzada', '111222', true);
        $this->assertCount(1, $res);
        $this->falla(fn () => $this->svc()->firmar($this->sec, [], true, 'avanzada', '111222', true), 'no hay documentos');
        $this->assertSame(3, Documento::query()->where('estado', 'vigente')->count());
    }

    public function test_validaciones_de_seleccion_y_permiso(): void
    {
        $this->oficio($this->e1, 'Oficio A');
        $ids = $this->ids($this->sec);
        $this->falla(fn () => $this->svc()->firmar($this->sec, $ids, false, 'avanzada', '111222', false), 'revisó');
        $this->falla(fn () => $this->svc()->firmar($this->sec, $ids, false, 'avanzada', '12', true), 'otp');
        $this->falla(fn () => $this->svc()->firmar($this->sec, [], false, 'avanzada', '111222', true), 'al menos un documento');
        $this->falla(fn () => $this->svc()->firmar($this->sec, [999999], false, 'avanzada', '111222', true), 'ya no está pendiente');
        $this->falla(fn () => $this->svc()->firmar($this->dom, $ids, false, 'avanzada', '111222', true), 'no está habilitado');
        $this->falla(fn () => $this->svc()->firmar($this->usuario('alcalde'), $ids, false, 'avanzada', '111222', true), 'ya no está pendiente'); // paso ajeno
        config(['sgd.firma_lote_max' => 1]);
        $this->oficio($this->e2, 'Oficio B');
        $this->falla(fn () => $this->svc()->firmar($this->sec, $this->ids($this->sec), false, 'avanzada', '111222', true), 'máximo');
        $this->assertSame(0, Documento::query()->where('estado', 'vigente')->count());
    }

    public function test_un_fallo_del_servicio_de_firma_detiene_el_lote_con_firma_avanzada(): void
    {
        foreach (['A', 'B', 'C'] as $t) {
            $this->oficio($this->e1, "Oficio $t");
        }
        $res = $this->svc()->firmar($this->sec, $this->ids($this->sec), false, 'avanzada', '000000', true); // el simulador rechaza este OTP
        $vals = array_values($res);
        $this->assertFalse($vals[0]['ok']);
        $this->assertStringContainsString('inválido', $vals[0]['mensaje']);
        $this->assertStringContainsString('No procesado', $vals[1]['mensaje']);
        $this->assertStringContainsString('No procesado', $vals[2]['mensaje']);
        $this->assertSame(0, Documento::query()->where('estado', 'vigente')->count());
        $this->assertCount(3, $this->ids($this->sec), 'nada cambió');
    }

    public function test_lo_restringido_no_se_firma_en_lote(): void
    {
        $a = $this->oficio($this->e1, 'Oficio A');
        $b = $this->oficio($this->e1, 'Oficio B');
        app(AccesoRestringidoService::class)->restringir($this->dom, $this->e1, 'Contiene datos personales sensibles del solicitante', null, $b);
        $lista = $this->svc()->pendientes($this->sec, 'firma');
        $this->assertTrue($lista->firstWhere('documento.id', $b->id)['oculto'], 'sin autorización se enmascara');
        $res = $this->svc()->firmar($this->sec, [], true, 'avanzada', '111222', true);
        $this->assertCount(1, $res, 'con «todos» se excluye lo oculto');
        $this->assertSame('vigente', $a->refresh()->estado);
        $this->assertSame('en_flujo', $b->refresh()->estado);
        $manual = array_values($this->svc()->firmar($this->sec, $this->ids($this->sec), false, 'avanzada', '111222', true)); // elegido a mano: también se omite
        $this->assertFalse($manual[0]['ok']);
        $this->assertStringContainsString('restringido', $manual[0]['mensaje']);
        $this->assertSame('en_flujo', $b->refresh()->estado);
    }

    public function test_firma_simple_por_lote_solo_donde_el_tipo_la_admite(): void
    {
        $tes = Usuario::query()->where('rol', 'tesorero')->firstOrFail();
        $tipo = app(PagoConfigService::class)->tipo('egreso');
        $ds = app(DocumentoService::class);
        $simples = [];
        for ($i = 1; $i <= 2; $i++) {
            $doc = $ds->crear($this->e1, $tipo, null, "Egreso $i", $this->dom, [], '<p>Egreso '.$i.'</p>');
            $simples[] = $ds->definirFlujo($doc, 'agil', [], [$tes->id], [$tes->id => 'simple']);
        }
        $res = $this->svc()->firmar($tes, [], true, 'simple', null, true);
        $this->assertSame([true, true], array_values(array_column($res, 'ok')));
        $this->assertSame('simple', $simples[0]->refresh()->pasos->first()->firma_tipo);
        // un documento de firma avanzada no se firma como simple
        $this->oficio($this->e2, 'Oficio avanzado');
        $sec2 = $this->sec;
        $res = $this->svc()->firmar($sec2, $this->ids($sec2), false, 'simple', null, true);
        $this->assertFalse(array_values($res)[0]['ok']);
        $this->assertStringContainsString('no admite firma simple', array_values($res)[0]['mensaje']);
    }

    public function test_visar_por_lote_y_pantallas(): void
    {
        $ds = app(DocumentoService::class);
        $alc = $this->usuario('alcalde');
        foreach (['A', 'B'] as $t) {
            $doc = $ds->crear($this->e1, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, "Oficio $t", $this->dom);
            $ds->definirFlujo($doc, 'agil', [$alc->id], [$this->sec->id]);
        }
        $this->assertCount(0, $this->ids($this->sec), 'aún no firma: falta la visación');
        $this->comoFuncionario($alc)->get('/app/firmas')->assertOk()->assertSee('Para visar (2)')->assertSee('Mis firmas y visaciones');
        $ids = $this->ids($alc, 'visacion');
        $this->post('/app/firmas/visar', ['pasos' => $ids])->assertSessionHas('error'); // sin confirmar la revisión
        $this->post('/app/firmas/visar', ['pasos' => $ids, 'reviso' => 1, 'comentario' => 'Conforme'])->assertRedirect('/app/firmas')->assertSessionHas('ok');
        $this->assertCount(2, $this->ids($this->sec), 'tras visar, ahora le toca a la secretaria');
        auth()->forgetGuards();
        $this->comoFuncionario($this->sec)->get('/app/firmas')->assertOk()->assertSee('Para firmar (2)')->assertSee('Oficio A')->assertSee('Seleccionar todos');
        $this->post('/app/firmas/firmar', ['todos' => 1, 'tipo_firma' => 'avanzada', 'otp' => '111222', 'reviso' => 1])->assertRedirect('/app/firmas')->assertSessionHas('ok');
        $this->get('/app/firmas')->assertOk()->assertSee('Resultado del lote')->assertSee('Firmado y vigente')->assertSee('No tiene documentos por firmar');
        $this->post('/app/firmas/firmar', ['pasos' => [1], 'tipo_firma' => 'simple', 'reviso' => 1])->assertSessionHas('error'); // simple sin confirmar
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/firmas')->assertForbidden();
    }
}
