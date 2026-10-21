<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Listado de documentos vigentes: todos los vigentes, con filtros (decreto/oficio, año actual por defecto), búsqueda y orden. */
class VigentesListadoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private function preparar(): array
    {
        $this->sembrar();
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $oficio = $this->documentoVigente($e, $dom, 'OFICIO');
        $decreto = $this->documentoVigente($e, $dom, 'DEC_SIAPER');
        $oficio->forceFill(['titulo' => 'Oficio sobre canchas', 'tipo_materia' => 'Informa', 'macrotema' => 'Informa/Comunica'])->save();
        $decreto->forceFill(['titulo' => 'Decreto autoriza cometido', 'tipo_materia' => 'Autoriza Cometido', 'macrotema' => 'Autoriza'])->save();

        return [$dom, $oficio->refresh(), $decreto->refresh()];
    }

    public function test_muestra_por_defecto_el_ano_actual_y_el_estilo_de_tabla_con_las_columnas(): void
    {
        [$dom, $oficio, $decreto] = $this->preparar();
        $r = $this->comoFuncionario($dom)->get('/app/vigentes')->assertOk();
        $r->assertSee('Oficio sobre canchas')->assertSee('Decreto autoriza cometido')->assertSee('sgd-tabla-azul', false);
        foreach (['N°', 'Fecha', 'Tipo', 'Dirección', 'Materia', 'Tipo de materia', 'Macrotema', 'Expediente', 'Documento'] as $c) {
            $r->assertSee($c);
        }
        $r->assertSee('Ver aquí')->assertSee('Autoriza Cometido')->assertSee('<option value="'.now('America/Santiago')->year.'" selected', false);
        // un documento de otro año no aparece por defecto, sí con «Todos»
        $oficio->forceFill(['anio' => now()->year - 2, 'fecha_firma' => now()->subYears(2)])->save();
        $this->comoFuncionario($dom)->get('/app/vigentes')->assertDontSee('Oficio sobre canchas')->assertSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?anio=todos')->assertSee('Oficio sobre canchas');
        $this->comoFuncionario($dom)->get('/app/vigentes?anio='.(now()->year - 2))->assertSee('Oficio sobre canchas')->assertDontSee('Decreto autoriza cometido');
    }

    public function test_filtra_por_decreto_oficio_y_tipo_en_particular(): void
    {
        [$dom, $oficio, $decreto] = $this->preparar();
        $this->comoFuncionario($dom)->get('/app/vigentes?tipo=decretos')->assertSee('Decreto autoriza cometido')->assertDontSee('Oficio sobre canchas');
        $this->comoFuncionario($dom)->get('/app/vigentes?tipo=oficios')->assertSee('Oficio sobre canchas')->assertDontSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?tipo='.$oficio->tipo_documento_id)->assertSee('Oficio sobre canchas')->assertDontSee('Decreto autoriza cometido');
    }

    public function test_busca_por_texto_numero_expediente_materia_y_macrotema(): void
    {
        [$dom, $oficio, $decreto] = $this->preparar();
        $this->comoFuncionario($dom)->get('/app/vigentes?q=canchas')->assertSee('Oficio sobre canchas')->assertDontSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?q='.$decreto->expediente->codigo)->assertSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?q='.$decreto->numero)->assertOk()->assertSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?macrotema=Autoriza')->assertSee('Decreto autoriza cometido')->assertDontSee('Oficio sobre canchas');
        $this->comoFuncionario($dom)->get('/app/vigentes?tipo_materia=Informa')->assertSee('Oficio sobre canchas')->assertDontSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?unidad='.$decreto->expediente->unidad_id)->assertSee('Decreto autoriza cometido');
        $this->comoFuncionario($dom)->get('/app/vigentes?q=nadadenada')->assertSee('No hay documentos vigentes con estos filtros');
        $this->comoFuncionario($dom)->get('/app/vigentes?desde='.now()->addDay()->format('Y-m-d'))->assertSee('No hay documentos vigentes');
    }

    public function test_ordena_por_columnas_permitidas_y_rechaza_las_demas(): void
    {
        [$dom] = $this->preparar();
        $a = $this->comoFuncionario($dom)->get('/app/vigentes?orden=materia&dir=asc')->assertOk()->getContent();
        $b = $this->comoFuncionario($dom)->get('/app/vigentes?orden=materia&dir=desc')->assertOk()->getContent();
        $this->assertLessThan(strpos($a, 'Oficio sobre canchas'), strpos($a, 'Decreto autoriza cometido'));
        $this->assertLessThan(strpos($b, 'Decreto autoriza cometido'), strpos($b, 'Oficio sobre canchas'));
        $this->comoFuncionario($dom)->get('/app/vigentes?orden=password')->assertSessionHasErrors('orden');
        $this->comoFuncionario($dom)->get('/app/vigentes?dir=sideways')->assertSessionHasErrors('dir');
        $this->comoFuncionario($dom)->get('/app/vigentes?q=%27%20or%201=1--&anio=todos')->assertOk();
    }

    public function test_muestra_los_vigentes_de_todas_las_direcciones_pero_solo_abre_los_que_puede(): void
    {
        [$dom, $oficio, $decreto] = $this->preparar();
        $decreto->forceFill(['sensible' => true])->save();
        $otra = $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app/vigentes')->assertOk();
        $otra->assertSee('Oficio sobre canchas')->assertSee('Contiene datos sensibles')->assertDontSee('Decreto autoriza cometido')->assertDontSee('Ver aquí')->assertSee('Sin acceso a su contenido');
        $this->comoFuncionario($dom)->get('/app/vigentes')->assertSee('Ver aquí');
        // un expediente restringido se muestra como restringido, sin título ni código
        $decreto->expediente->forceFill(['restringido' => true, 'restringido_motivo' => 'prueba'])->save();
        $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app/vigentes')->assertSee('Documento restringido')->assertDontSee($decreto->codigo_validacion);
    }

    public function test_el_titulo_de_la_materia_se_clasifica_desde_el_documento(): void
    {
        [$dom, $oficio] = $this->preparar();
        $r = $this->comoFuncionario($dom)->post('/app/documentos/'.$oficio->id, ['titulo' => $oficio->titulo, 'html' => '<p>texto</p>', 'nivel_acceso' => 'reservado', 'tipo_materia' => '  Aprueba Convenio ', 'macrotema' => 'Aprueba']);
        $r->assertSessionDoesntHaveErrors();
        $d = Documento::query()->find($oficio->id);
        $this->assertSame('Informa', $d->tipo_materia, 'un documento vigente no es editable: no cambia');
    }
}
