<?php

namespace Tests\Feature;

use App\Mail\NotificacionParte;
use App\Models\ComunicacionOficial;
use App\Models\Derivacion;
use App\Models\DocDigitalEntidad;
use App\Models\DocDigitalUsuario;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Notificacion;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Expedientes iniciados de oficio por un funcionario, sus partes y la notificación al concluir el procedimiento. */
class ExpedienteOficioTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function crear(array $extra = []): Expediente
    {
        $f = $this->usuario('funcionario', 1);
        $this->comoFuncionario($f)->post('/app/expedientes', $extra + ['asunto' => 'Modificación de la ordenanza de ferias', 'descripcion' => 'Se revisa la ordenanza', 'resultado' => 'Decreto', 'fundamento' => 'Ley 18.695'])->assertSessionHasNoErrors();

        return Expediente::query()->where('origen', 'oficio')->latest('id')->firstOrFail();
    }

    public function test_un_funcionario_con_permiso_inicia_un_expediente_de_oficio(): void
    {
        $f = $this->usuario('funcionario', 1);
        $this->comoFuncionario($f)->get('/app/expedientes/nuevo')->assertOk()->assertSee('Iniciar un expediente de oficio');
        $e = $this->crear();
        $this->assertNull($e->solicitud_id);
        $this->assertSame($f->id, $e->responsable_id);
        $this->assertSame($f->unidad_id, $e->unidad_id);
        $this->assertSame('Decreto', $e->metadatos['procedimiento']['resultado_esperado']);
        $this->assertSame('Procedimiento iniciado de oficio', $e->metadatos['caracterizacion']['tipo_expediente']);
        $this->assertStringStartsWith('EXP-', $e->codigo);
        $this->assertDatabaseHas('audit_log', ['accion' => 'expediente.crear_oficio']);
        $this->comoFuncionario($f)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('iniciado de oficio')->assertSee('Partes y notificación');
        $this->comoFuncionario($f)->get('/app/')->assertSee('Iniciar un expediente de oficio');
    }

    public function test_sin_permiso_o_sin_unidad_no_se_puede_iniciar(): void
    {
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/expedientes/nuevo')->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->post('/app/expedientes', ['asunto' => 'Asunto válido'])->assertForbidden();
        $f = $this->usuario('funcionario', 1);
        $f->forceFill(['unidad_id' => null])->save();
        $this->comoFuncionario($f)->get('/app/expedientes/nuevo')->assertOk()->assertSee('no está asignada a una unidad');
        $this->comoFuncionario($f)->post('/app/expedientes', ['asunto' => 'Asunto válido largo'])->assertSessionHas('error');
        $g = $this->usuario('funcionario', 2);
        $this->comoFuncionario($g)->post('/app/expedientes', ['asunto' => 'Cort'])->assertSessionHasErrors('asunto');
        $this->comoFuncionario($g)->post('/app/expedientes', ['asunto' => "Dos\nlíneas"])->assertSessionHas('error');
    }

    public function test_el_permiso_se_puede_quitar_a_un_rol(): void
    {
        $f = $this->usuario('funcionario', 1);
        \DB::table('usuario_permisos')->insert(['usuario_id' => $f->id, 'permiso' => 'expedientes.crear', 'efecto' => 'denegar', 'created_at' => now(), 'updated_at' => now()]);
        $this->comoFuncionario($f->refresh())->get('/app/expedientes/nuevo')->assertForbidden();
    }

    public function test_solo_quien_opera_el_expediente_agrega_partes(): void
    {
        $e = $this->crear();
        $otra = $this->usuario('secretario');
        $this->comoFuncionario($otra)->post("/app/expedientes/{$e->id}/partes", ['tipo' => 'externo', 'nombre' => 'Empresa X', 'correo' => 'a@b.cl', 'canal' => 'correo'])->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/expedientes/{$e->id}/partes", ['tipo' => 'externo', 'nombre' => 'Empresa X', 'canal' => 'presencial'])->assertForbidden();
    }

    public function test_valida_cada_tipo_de_parte(): void
    {
        $e = $this->crear();
        $f = $this->usuario('funcionario', 1);
        $url = "/app/expedientes/{$e->id}/partes";
        $c = $this->comoFuncionario($f);
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'canal' => 'casilla_unica'])->assertSessionHas('error'); // sin RUN
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '11111111-2', 'canal' => 'casilla_unica'])->assertSessionHas('error'); // RUN inválido
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '12345678-5', 'canal' => 'correo'])->assertSessionHas('error'); // correo sin dirección
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '12345678-5', 'canal' => 'interno'])->assertSessionHas('error'); // canal ajeno
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '12345678-5', 'canal' => 'casilla_unica'])->assertSessionHas('ok');
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '12345678-5', 'canal' => 'casilla_unica'])->assertSessionHas('error'); // repetida
        $c->post($url, ['tipo' => 'externo', 'nombre' => 'Empresa X', 'correo' => "mal\ncorreo@x.cl", 'canal' => 'correo'])->assertSessionHas('error');
        $c->post($url, ['tipo' => 'funcionario', 'usuario_id' => 999999, 'canal' => 'interno'])->assertSessionHas('error');
        $c->post($url, ['tipo' => 'organismo', 'dd_entidad_id' => 777, 'canal' => 'docdigital'])->assertSessionHas('error'); // no está en el directorio
        $this->assertSame(1, ExpedienteParte::query()->count());
    }

    public function test_notifica_a_cada_parte_por_su_canal(): void
    {
        Mail::fake();
        Queue::fake();
        $this->ddConfig();
        $this->ddFake();
        DocDigitalEntidad::query()->create(['entidad_id' => 7001, 'nombre' => 'Gobierno Regional de Valparaíso', 'vigente' => true]);
        DocDigitalUsuario::query()->create(['usuario_id' => 12, 'nombre' => 'Beto GORE', 'entidad_id' => 7001, 'vigente' => true]);
        $f = $this->usuario('funcionario', 1);
        $colega = $this->usuario('funcionario', 2);
        $e = $this->crear();
        $url = "/app/expedientes/{$e->id}/partes";
        $c = $this->comoFuncionario($f);
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecina Uno', 'run' => '12345678-5', 'canal' => 'casilla_unica'])->assertSessionHas('ok');
        $c->post($url, ['tipo' => 'ciudadano', 'nombre' => 'Vecino Dos', 'run' => '9876543-3', 'correo' => 'v2@example.com', 'canal' => 'correo'])->assertSessionHas('ok');
        $c->post($url, ['tipo' => 'externo', 'nombre' => 'Empresa X', 'canal' => 'presencial'])->assertSessionHas('ok');
        $c->post($url, ['tipo' => 'funcionario', 'usuario_id' => $colega->id, 'canal' => 'interno'])->assertSessionHas('ok');
        $c->post($url, ['tipo' => 'organismo', 'dd_entidad_id' => 7001, 'dd_usuarios' => [12], 'canal' => 'docdigital'])->assertSessionHas('ok');
        $doc = $this->documentoVigente($e, $f);
        $doc->forceFill(['pdf_firmado_ruta' => $doc->pdf_ruta, 'pdf_firmado_sha256' => $doc->pdf_sha256])->save();
        $ids = ExpedienteParte::query()->pluck('id')->all();
        // aún abierto: no se notifica
        $c->post("/app/expedientes/{$e->id}/notificar-partes", ['partes' => $ids, 'documentos' => [$doc->id]])->assertSessionHas('error');
        app(ExpedienteService::class)->cerrar($e);
        $c->post("/app/expedientes/{$e->id}/notificar-partes", ['partes' => $ids, 'documentos' => [$doc->id]])->assertSessionHas('ok');
        $this->assertSame(5, Notificacion::query()->where('expediente_id', $e->id)->count());
        $por = Notificacion::query()->where('expediente_id', $e->id)->get()->keyBy('canal');
        $this->assertSame('correo', $por['correo']->canal);
        $this->assertSame('v2@example.com', $por['correo']->destinatario_correo);
        $this->assertSame('pendiente', $por['presencial']->estado);
        $this->assertSame('pendiente', $por['casilla_unica']->estado); // Casilla en modo outbox: queda pendiente hasta registrar evidencia
        $this->assertSame('enviada', $por['interno']->estado);
        $der = Derivacion::query()->where('expediente_id', $e->id)->where('a_usuario_id', $colega->id)->first();
        $this->assertSame('conocimiento', $der->accion);
        $this->assertSame('pendiente', $der->estado);
        $this->assertSame('docdigital', $por['docdigital']->canal);
        $com = ComunicacionOficial::query()->find($por['docdigital']->comunicacion_id);
        $this->assertSame(7001, (int) $com->destino_entidad_id);
        $this->assertSame(12, json_decode($com->destino_usuarios, true)[0]['id']);
        // no se duplica
        $c->post("/app/expedientes/{$e->id}/notificar-partes", ['partes' => $ids, 'documentos' => [$doc->id]])->assertSessionHas('error');
        // una parte ya notificada no se puede quitar
        $this->comoFuncionario($f)->post("/app/expedientes/{$e->id}/partes/{$ids[0]}/quitar")->assertSessionHas('error');
        $this->comoFuncionario($f)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('Vecina Uno')->assertSee('Gobierno Regional de Valparaíso')->assertSee('***678-5');
    }

    public function test_el_correo_a_una_parte_no_adjunta_documentos_y_enlaza_a_la_validacion(): void
    {
        Queue::fake();
        $e = $this->crear();
        $f = $this->usuario('funcionario', 1);
        $this->comoFuncionario($f)->post("/app/expedientes/{$e->id}/partes", ['tipo' => 'externo', 'nombre' => 'Empresa X', 'correo' => 'x@empresa.cl', 'canal' => 'correo'])->assertSessionHas('ok');
        $doc = $this->documentoVigente($e, $f);
        app(ExpedienteService::class)->cerrar($e);
        $this->comoFuncionario($f)->post("/app/expedientes/{$e->id}/notificar-partes", ['partes' => [ExpedienteParte::query()->value('id')], 'documentos' => [$doc->id]])->assertSessionHas('ok');
        $n = Notificacion::query()->where('canal', 'correo')->firstOrFail();
        $m = new NotificacionParte($n->load('documento'));
        $html = $m->render();
        $this->assertStringContainsString('Empresa X', $html);
        $this->assertStringContainsString($doc->codigo_validacion, $html);
        $this->assertSame([], $m->attachments());
    }
}
