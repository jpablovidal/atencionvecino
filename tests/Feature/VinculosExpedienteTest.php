<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Expediente;
use App\Models\ExpedienteVinculo;
use App\Models\Rol;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\ArchivoMunicipalService;
use App\Services\ExpedienteService;
use App\Services\ExpedienteVinculoService;
use App\Services\SolicitudService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class VinculosExpedienteTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Usuario $dom;

    private Expediente $origen;

    private Expediente $destino;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->dom = $this->usuario('funcionario', 1); // DOM
        $this->origen = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->dom);
        $this->documentoVigente($this->origen, $this->dom);
        $s2 = app(SolicitudService::class)->ingresar(['run' => '20000002-1', 'nombres' => 'Otra', 'apellidos' => 'Vecina'], Tramite::query()->with('anexos')->findOrFail(1), 'otra@example.com', 'Otro permiso de edificación en otro terreno.', 'correo',
            [1 => $this->archivo('p2.pdf', $this->pdfValido('p2')), 2 => $this->archivo('d2.pdf', $this->pdfValido('d2'))], []);
        $this->destino = app(ExpedienteService::class)->crearDesdeSolicitud($s2, $this->dom);
    }

    private function svc(): ExpedienteVinculoService
    {
        return app(ExpedienteVinculoService::class);
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

    private function vinculo(): ExpedienteVinculo
    {
        return $this->svc()->vincularExpediente($this->dom, $this->destino, $this->origen->id, 'Reutilizar el proceso de un permiso anterior');
    }

    public function test_buscar_solo_muestra_lo_que_la_persona_puede_ver(): void
    {
        $r = $this->svc()->buscarExpedientes($this->dom, $this->destino, 'planos');
        $this->assertSame([$this->origen->id], $r->pluck('id')->all()); // por el título del archivo de la otra solicitud
        $this->assertSame([], $this->svc()->buscarExpedientes($this->dom, $this->destino, 'pl')->all(), 'mínimo 3 letras');
        $this->assertNotContains($this->destino->id, $this->svc()->buscarExpedientes($this->dom, $this->destino, 'EXP-')->pluck('id')->all(), 'no se ofrece a sí mismo');
        $dideco = $this->usuario('funcionario', 3); // otra dirección: no ve estos expedientes
        $this->assertSame([], $this->svc()->buscarExpedientes($dideco, $this->destino, 'EXP-')->all());
    }

    public function test_vincular_exige_motivo_permiso_expediente_abierto_y_no_duplicar(): void
    {
        $this->falla(fn () => $this->svc()->vincularExpediente($this->dom, $this->destino, $this->origen->id, 'corto'), 'motivo');
        $this->falla(fn () => $this->svc()->vincularExpediente($this->usuario('funcionario', 3), $this->destino, $this->origen->id, 'Reutilizar el proceso anterior'), 'no tiene permiso');
        $this->falla(fn () => $this->svc()->vincularExpediente($this->dom, $this->destino, $this->destino->id, 'Reutilizar el proceso anterior'), 'no puede asociarlo');
        $this->vinculo();
        $this->falla(fn () => $this->vinculo(), 'ya está asociado');
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('accion', 'expediente.vincular')->count());
        app(ExpedienteService::class)->cerrar($this->destino->fresh());
        $this->falla(fn () => $this->svc()->vincularExpediente($this->dom, $this->destino->fresh(), 99999, 'Reutilizar el proceso anterior'), 'cerrado');
    }

    public function test_incorpora_copias_con_origen_y_huella(): void
    {
        $v = $this->vinculo();
        $c = $this->svc()->contenido($this->dom, $v);
        $this->assertTrue($c['acceso']);
        $this->assertCount(1, $c['documentos']);
        $docId = $c['documentos'][0]['id'];
        $arch = Archivo::query()->where('expediente_id', $this->origen->id)->where('ambito', 'anexo')->firstOrFail();
        $antes = Archivo::query()->where('expediente_id', $this->destino->id)->count();
        $n = $this->svc()->incorporar($this->dom, $v, [$docId], [$arch->id]);
        $this->assertSame(2, $n);
        $copias = Archivo::query()->where('expediente_id', $this->destino->id)->where('vinculo_id', $v->id)->get();
        $this->assertCount(2, $copias);
        $this->assertSame($antes + 2, Archivo::query()->where('expediente_id', $this->destino->id)->count());
        $cp = $copias->firstWhere('sha256', $arch->sha256);
        $this->assertNotNull($cp);
        $this->assertNotSame($arch->ruta, $cp->ruta, 'es una copia independiente');
        $this->assertStringContainsString($this->origen->codigo, (string) $cp->origen_descripcion);
        $this->assertSame($arch->sha256, $cp->origen_sha256);
        $this->falla(fn () => $this->svc()->incorporar($this->dom, $v, [], []), 'al menos');
        $this->falla(fn () => $this->svc()->incorporar($this->dom, $v, [], [999999]), 'seleccione solo');
        // el contenido de OTRO expediente no se puede copiar por un vínculo ajeno
        $otro = Archivo::query()->where('expediente_id', $this->destino->id)->where('ambito', 'anexo')->firstOrFail();
        $this->falla(fn () => $this->svc()->incorporar($this->dom, $v, [], [$otro->id]), 'seleccione solo');
    }

    public function test_lo_restringido_no_se_copia_sin_autorizacion_y_la_copia_hereda_la_restriccion(): void
    {
        $arch = Archivo::query()->where('expediente_id', $this->origen->id)->where('ambito', 'anexo')->firstOrFail();
        app(AccesoRestringidoService::class)->restringir($this->dom, $this->origen, 'Contiene datos personales sensibles del solicitante', $arch);
        $dom2 = $this->usuario('funcionario', 2); // misma dirección, sin autorización sobre el archivo
        $this->destino->forceFill(['responsable_id' => $dom2->id])->save();
        $v = $this->svc()->vincularExpediente($dom2, $this->destino->fresh(), $this->origen->id, 'Reutilizar el proceso anterior');
        $c = $this->svc()->contenido($dom2, $v);
        $enmascarado = collect($c['archivos'])->firstWhere('id', $arch->id);
        $this->assertTrue($enmascarado['oculto']);
        $this->assertSame('Archivo restringido', $enmascarado['nombre']);
        $this->falla(fn () => $this->svc()->incorporar($dom2, $v, [], [$arch->id]), 'autoriz');
        $this->assertSame(0, Archivo::query()->where('vinculo_id', $v->id)->count());
        // con autorización: se copia, queda restringida y autorizada la persona que la incorporó
        app(AccesoRestringidoService::class)->autorizar($this->dom, $this->origen->fresh(), $dom2->id, 'Necesita conocerlo para el nuevo trámite', null, $arch->fresh());
        $this->assertSame(1, $this->svc()->incorporar($dom2, $v, [], [$arch->id]));
        $copia = Archivo::query()->where('vinculo_id', $v->id)->firstOrFail();
        $this->assertTrue($copia->restringido);
        $this->assertTrue(AccesoRestringido::query()->where('archivo_id', $copia->id)->where('usuario_id', $dom2->id)->exists());
        $this->assertFalse(app(AccesoRestringidoService::class)->autorizado($this->usuario('funcionario', 3), $this->destino->fresh(), $copia));
    }

    public function test_microformas_exigen_el_permiso_del_archivo_municipal_y_dejan_custodia(): void
    {
        $arch = Usuario::query()->where('rol', 'arch_gestion')->firstOrFail();
        $m = app(ArchivoMunicipalService::class)->crear($arch, ['titulo' => 'Decretos 2019', 'descripcion' => 'Microfilm', 'tipo_soporte' => 'microfilm_16mm', 'unidad_id' => 1, 'serie' => 'Decretos', 'ubicacion' => 'Bodega 1']);
        app(ArchivoMunicipalService::class)->subirArchivos($m, $arch, [$this->archivo('rollo.pdf', $this->pdfValido('rollo'))]);
        $this->falla(fn () => $this->svc()->vincularMicroforma($this->dom, $this->destino, $m->id, 'Antecedentes históricos del terreno'), 'archivo municipal');
        $this->assertSame([], $this->svc()->buscarMicroformas($this->dom, $this->destino, 'Decretos')->all());
        $rol = Rol::query()->create(['clave' => 'dom_archivo', 'nombre' => 'DOM con archivo', 'rol_base' => 'funcionario', 'sistema' => false, 'activo' => true]);
        foreach (['expedientes.ver', 'expedientes.operar', 'expedientes.vincular', 'archivo.ver'] as $p) {
            DB::table('rol_permisos')->insert(['rol_id' => $rol->id, 'permiso' => $p]);
        }
        $this->dom->forceFill(['rol' => 'dom_archivo'])->save();
        $u = Usuario::query()->find($this->dom->id);
        $this->assertSame([$m->id], $this->svc()->buscarMicroformas($u, $this->destino, 'Decretos')->pluck('id')->all());
        $v = $this->svc()->vincularMicroforma($u, $this->destino, $m->id, 'Antecedentes históricos del terreno');
        $a = $m->archivos()->first();
        $this->assertSame(1, $this->svc()->incorporar($u, $v, [], [$a->id]));
        $copia = Archivo::query()->where('vinculo_id', $v->id)->firstOrFail();
        $this->assertStringContainsString('microforma', mb_strtolower((string) $copia->titulo));
        $this->assertTrue($m->eventos()->where('tipo', 'consulta')->exists(), 'cadena de custodia de la microforma');
        $this->falla(fn () => $this->svc()->incorporar($u, $v, [123], []), 'solo tienen archivos');
    }

    public function test_pantallas_http_y_desvincular_conserva_las_copias(): void
    {
        $this->comoFuncionario($this->dom)->get("/app/expedientes/{$this->destino->id}/asociar?q=planos")->assertOk()->assertSee($this->origen->codigo);
        $this->post("/app/expedientes/{$this->destino->id}/vinculos", ['tipo' => 'expediente', 'id' => $this->origen->id, 'motivo' => 'Reutilizar el proceso anterior'])->assertSessionHas('ok');
        $v = ExpedienteVinculo::query()->firstOrFail();
        $this->get("/app/vinculos/{$v->id}")->assertOk()->assertSee('Incorporar copias');
        $arch = Archivo::query()->where('expediente_id', $this->origen->id)->where('ambito', 'anexo')->firstOrFail();
        $this->post("/app/vinculos/{$v->id}/incorporar", ['archivos' => [$arch->id]])->assertSessionHas('ok');
        $this->get("/app/expedientes/{$this->destino->id}")->assertOk()->assertSee('Expedientes y microformas asociados')->assertSee($this->origen->codigo);
        $this->get("/app/expedientes/{$this->origen->id}")->assertOk()->assertSee('fue reutilizado en');
        $this->post("/app/vinculos/{$v->id}/quitar")->assertRedirect();
        $this->assertSame(0, ExpedienteVinculo::query()->count());
        $this->assertSame(1, Archivo::query()->where('expediente_id', $this->destino->id)->whereNotNull('vinculo_id')->count(), 'la copia se conserva');
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario', 3))->get("/app/expedientes/{$this->destino->id}/asociar")->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get("/app/expedientes/{$this->destino->id}/asociar")->assertForbidden();
        $this->assertTrue(Run::normalizar('10000001-6') !== null);
    }
}
