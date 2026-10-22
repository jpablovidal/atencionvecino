<?php

namespace Tests\Feature;

use App\Models\Solicitud;
use App\Models\Tramite;
use App\Services\ExpedienteService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Solicitudes presentadas por un tercero a nombre de una persona asistida. */
class SolicitudAsistidaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    /** @return array<string,mixed> */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'tramite_id' => 1, 'correo' => 'tercero@example.com', 'detalle' => 'Solicito permiso de edificación para el terreno de mi madre.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si',
            'planos' => null, 'en_nombre_de' => '1', 'tercero_run' => '12345678-5', 'tercero_nombre' => 'María Pérez Soto', 'tercero_correo' => 'maria@example.com', 'tercero_relacion' => 'familiar',
            'tercero_acreditacion' => 'Hijo de la señora María, adulta mayor; poder simple adjunto', 'tercero_declara' => '1',
        ], $extra);
    }

    private function enviar(array $extra = [])
    {
        $t = Tramite::query()->with('anexos')->findOrFail(1);
        $files = [];
        foreach ($t->anexos as $a) {
            $files['anexo_'.$a->id] = $this->archivo('a'.$a->id.'.pdf');
        }

        return $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->datos($extra) + $files);
    }

    public function test_formulario_ofrece_presentar_a_nombre_de_otra_persona(): void
    {
        $this->comoCiudadano()->get('/ciudadano/nueva/1')->assertOk()->assertSee('a nombre de otra persona')->assertSee('RUN de esa persona')->assertSee('Su relación con esa persona');
    }

    public function test_guarda_al_tercero_la_declaracion_y_la_relacion(): void
    {
        $this->enviar()->assertSessionHasNoErrors();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $this->assertTrue($s->en_nombre_de);
        $this->assertSame('20000001-'.Run::dv('20000001'), $s->run); // quien presenta es siempre la persona con sesión
        $this->assertSame('12345678-5', $s->interesado_run);
        $this->assertSame('María Pérez Soto', $s->interesado_nombre);
        $this->assertSame('familiar', $s->relacion);
        $this->assertNotNull($s->declaracion_en);
        $this->assertDatabaseHas('audit_log', ['accion' => 'solicitud.en_nombre_de']);
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->assertOk()->assertSee('A nombre de')->assertSee('María Pérez Soto')->assertSee('Familiar');
    }

    public function test_sin_marcar_la_opcion_los_datos_del_tercero_se_ignoran(): void
    {
        $this->enviar(['en_nombre_de' => null])->assertSessionHasNoErrors();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $this->assertFalse($s->en_nombre_de);
        $this->assertNull($s->interesado_run);
    }

    public function test_exige_los_datos_y_la_declaracion(): void
    {
        $this->enviar(['tercero_run' => ''])->assertSessionHasErrors('tercero_run');
        $this->enviar(['tercero_nombre' => ''])->assertSessionHasErrors('tercero_nombre');
        $this->enviar(['tercero_relacion' => 'amigo del barrio'])->assertSessionHasErrors('tercero_relacion');
        $this->enviar(['tercero_acreditacion' => ''])->assertSessionHasErrors('tercero_acreditacion');
        $this->enviar(['tercero_declara' => null])->assertSessionHasErrors('tercero_declara');
        $this->enviar(['tercero_run' => '11111111-2'])->assertSessionHas('error'); // RUN inválido
        $this->enviar(['tercero_run' => $this->ciudadano()['run']])->assertSessionHas('error'); // no puede ser el propio
        $this->enviar(['tercero_acreditacion' => 'ok'])->assertSessionHas('error'); // muy corta
        $this->enviar(['tercero_correo' => "x\ny@z.cl"])->assertSessionHas('error');
        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_el_funcionario_ve_a_nombre_de_quien_se_presento_y_el_expediente_lo_registra(): void
    {
        $this->enviar();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $f = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $f);
        $this->assertStringContainsString('Tercero que actúa', $e->metadatos['poderes']['representacion']);
        $this->assertSame('12345678-5', $e->metadatos['poderes']['interesado_run']);
        $this->comoFuncionario($f)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('A nombre de')->assertSee('María Pérez Soto')->assertSee('el sistema no verifica el poder');
    }

    public function test_el_comprobante_pdf_menciona_a_la_persona_asistida(): void
    {
        $this->enviar();
        $s = Solicitud::query()->with('tramite')->latest('id')->firstOrFail();
        $html = view('pdf.comprobante', ['s' => $s, 'anexos' => [], 'codigo' => 'X', 'qr' => '', 'url' => 'https://x'])->render();
        $this->assertStringContainsString('Presentada a nombre de', $html);
        $this->assertStringContainsString('María Pérez Soto', $html);
    }

    public function test_un_funcionario_no_tramita_una_solicitud_hecha_a_su_nombre(): void
    {
        $f = $this->usuario('funcionario', 1);
        $this->enviar(['tercero_run' => $f->run]);
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $this->assertSame($f->run, $s->interesado_run);
        $this->comoFuncionario($f)->get("/app/solicitudes/{$s->folio}")->assertForbidden();
        $this->comoFuncionario($f)->post("/app/solicitudes/{$s->folio}/asignar")->assertStatus(403);
        $g = $this->usuario('funcionario', 2);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $g);
        $this->comoFuncionario($f)->get("/app/expedientes/{$e->id}")->assertForbidden(); // tampoco lo ve como parte de su unidad
        $this->assertSame(1, Solicitud::query()->dePersona($f->run)->count());
    }
}
