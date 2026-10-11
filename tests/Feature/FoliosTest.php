<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Services\ExpedienteService;
use App\Services\FolioService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class FoliosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function oficio(): TipoDocumento
    {
        return TipoDocumento::query()->where('codigo', 'OFICIO')->firstOrFail();
    }

    private function emitir(string $codigo = 'OFICIO'): Documento
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);

        return $this->documentoVigente($e, $dom, $codigo);
    }

    public function test_formato_por_defecto_conserva_la_forma_historica(): void
    {
        $this->assertSame('Oficio N° 01/2026', app(FolioService::class)->formatear($this->oficio(), 1, 2026));
        $this->assertSame('Oficio N° 01/2026', $this->emitir()->folio);
    }

    public function test_formatos_personalizados_relleno_e_inicio(): void
    {
        $t = $this->oficio();
        $t->forceFill(['folio_formato' => '{TIPO}-{N}/{AA}', 'folio_digitos' => 4, 'folio_inicio' => 100])->save();
        $this->assertSame('OFICIO-0100/26', app(FolioService::class)->formatear($t, 100, 2026));
        $d = $this->emitir();
        $this->assertSame(['OFICIO-0100/26', 100], [$d->folio, $d->numero]);
        $t->forceFill(['folio_digitos' => 0])->save();
        $this->assertSame('OFICIO-7/26', app(FolioService::class)->formatear($t, 7, 2026));
    }

    public function test_el_folio_emitido_no_cambia_si_se_cambia_el_formato(): void
    {
        $d = $this->emitir();
        $antes = $d->folio;
        $this->oficio()->forceFill(['folio_formato' => 'Nº {N} de {AAAA} ({PREFIJO})'])->save();
        $this->assertSame($antes, $d->refresh()->folio);
        $this->assertSame($antes, $d->numeroFormateado());
        $this->assertStringContainsString('2', $this->emitir()->folio); // el siguiente usa el nuevo formato
        $this->assertStringContainsString('de 2026', Documento::query()->latest('id')->first()->folio);
    }

    public function test_validacion_de_formatos(): void
    {
        $s = app(FolioService::class);
        foreach ([['', 2, true], ['sin numero {AAAA}', 2, true], ['{N} {N} {AAAA}', 2, true], ['{N}/{AAAA} {EXTRA}', 2, true], ['{N}', 2, true], ['{PREFIJO} {N}<script>/{AAAA}', 2, true], ['{N}/{AAAA}', 9, true], [str_repeat('x', 90).'{N}{AAAA}', 2, true]] as [$f, $dg, $re]) {
            try {
                $s->validarFormato($f, $dg, $re);
                $this->fail("debió rechazar «$f»");
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
        $s->validarFormato('{N}', 2, false); // continuo: el año es opcional
        $s->validarFormato('Resolución Exenta N° {N}/{AA} ({TIPO})', 3, true);
        $this->addToAssertionCount(1);
    }

    public function test_numeracion_continua_sin_reinicio_anual(): void
    {
        $this->oficio()->forceFill(['folio_reinicio_anual' => false, 'folio_formato' => '{PREFIJO} {N}'])->save();
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00', 'America/Santiago'));
        $a = $this->emitir();
        Carbon::setTestNow(Carbon::parse('2027-02-10 12:00', 'America/Santiago'));
        $b = $this->emitir();
        Carbon::setTestNow();
        $this->assertSame([1, 2026, 'Oficio 01'], [$a->numero, $a->anio, $a->folio]);
        $this->assertSame([2, 2027, 'Oficio 02'], [$b->numero, $b->anio, $b->folio]);
        $this->assertSame([['anio' => 0, 'ultimo' => 2]], app(FolioService::class)->contadores($this->oficio()));
    }

    public function test_reinicio_anual_sigue_funcionando_y_tipos_son_independientes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 20:00', 'America/Santiago'));
        $a = $this->emitir();
        $o = $this->emitir('INSTRUCTIVO');
        Carbon::setTestNow(Carbon::parse('2027-01-02 09:00', 'America/Santiago'));
        $b = $this->emitir();
        Carbon::setTestNow();
        $this->assertSame([1, 2026], [$a->numero, $a->anio]);
        $this->assertSame(1, $o->numero);
        $this->assertSame([1, 2027], [$b->numero, $b->anio]);
    }

    public function test_ajuste_de_contador_solo_hacia_arriba_con_motivo(): void
    {
        $t = $this->oficio();
        $admin = $this->usuario('admin');
        $s = app(FolioService::class);
        $a = $this->emitir(); // contador 1
        $s->ajustarContador($t, (int) $a->anio, 49, 'Migración de folios desde sistema anterior', $admin);
        $this->assertSame(50, $this->emitir()->numero);
        foreach ([[2026, 10, 'Intento de bajar el contador'], [2026, 60, 'x'], [0, 70, 'Año cero en tipo con reinicio'], [1999, 70, 'Año fuera de rango']] as [$anio, $n, $m]) {
            try {
                $s->ajustarContador($t, $anio, $n, $m, $admin);
                $this->fail("debió rechazar el ajuste $anio/$n/$m (contadores: ".json_encode($s->contadores($t)).')');
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseHas('audit_log', ['accion' => 'folios.ajustar_contador']);
    }

    public function test_el_mantenedor_http_solo_para_quien_tiene_permiso(): void
    {
        $t = $this->oficio();
        $url = "/app/admin/folios/{$t->id}";
        $body = ['folio_formato' => 'OF-{N}/{AAAA}', 'folio_digitos' => 3, 'folio_inicio' => 5, 'folio_reinicio_anual' => 1];
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/folios')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->post($url, $body)->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/folios')->assertOk()->assertSee('Oficio N° 01/2026', false)->assertSee('Folios por tipo');
        $this->comoFuncionario($this->usuario('admin'))->get($url)->assertOk()->assertSee('Ejemplos con la configuración actual');
        $this->comoFuncionario($this->usuario('admin'))->post($url, array_merge($body, ['folio_formato' => 'sin-numero']))->assertSessionHas('error');
        $this->comoFuncionario($this->usuario('admin'))->post($url, $body)->assertSessionHas('ok');
        $this->assertSame(['OF-{N}/{AAAA}', 3, 5], [$t->refresh()->folio_formato, $t->folio_digitos, $t->folio_inicio]);
        $this->assertSame('OF-005/2026', $this->emitir()->folio);
        $this->comoFuncionario($this->usuario('admin'))->post($url.'/ajustar', ['anio' => 2026, 'ultimo' => 1, 'motivo' => 'bajar contador'])->assertSessionHas('error');
        $this->assertDatabaseHas('audit_log', ['accion' => 'folios.configurar']);
    }

    public function test_folio_unico_por_tipo_a_nivel_de_bd(): void
    {
        $d = $this->emitir();
        $this->expectException(QueryException::class);
        $otro = $this->emitir();
        DB::table('documentos')->where('id', $otro->id)->update(['folio' => $d->folio]);
    }

    public function test_la_hoja_de_certificado_y_la_validacion_muestran_el_folio(): void
    {
        $this->oficio()->forceFill(['folio_formato' => 'OF-{N}/{AAAA}', 'folio_digitos' => 3])->save();
        $d = $this->emitir();
        $this->get('/validar/'.$d->codigo_validacion)->assertSee('OF-001/2026');
        $this->assertSame('OF-001/2026', $d->numeroFormateado());
    }
}
