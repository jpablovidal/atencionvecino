<?php

namespace Tests\Feature;

use App\Models\ComunicacionOficial;
use App\Models\Derivacion;
use App\Models\Expediente;
use App\Models\Unidad;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Comunicación recibida por DocDigital: gestionar el expediente, derivarlo con una acción y cambiar su estado. */
class GestionComunicacionRecibidaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private function entrante(): array
    {
        $this->sembrar();
        config(['sgd.docdigital.mode' => 'mock']);
        $unidad = Unidad::query()->where('codigo', 'SECMUN')->first();
        $c = ComunicacionOficial::query()->create(['direccion' => 'entrante', 'institucion_codigo' => 'DEMO-GORE-VAL', 'institucion_nombre' => 'GORE', 'asunto' => 'Solicitud de informe', 'estado' => 'recibida',
            'evidencia_tipo' => 'docdigital_id', 'evidencia' => 'M-77', 'evidencia_en' => now(), 'unidad_id' => $unidad->id, 'acuse_estado' => 'pendiente']);
        $e = app(ExpedienteService::class)->crearDesdeComunicacion($c, $unidad);
        $c->forceFill(['expediente_id' => $e->id])->save();

        return [$e, $c->refresh()];
    }

    public function test_el_superadministrador_puede_gestionar_el_expediente_y_ver_los_botones(): void
    {
        [$e, $c] = $this->entrante();
        $s = $this->usuario('admin');
        $this->comoFuncionario($s)->get("/app/expedientes/{$e->id}")->assertForbidden(); // un administrador común no lee expedientes
        $s->forceFill(['superadmin' => true])->save();
        $r = $this->comoFuncionario($s->refresh())->get("/app/expedientes/{$e->id}")->assertOk();
        $r->assertSee('Acusar recibo por DocDigital')->assertDontSee('Asignarme este expediente')->assertSee('Acción que debe realizar')->assertSee('Cerrar expediente');
        $this->comoFuncionario($s)->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('ok');
        $this->assertSame('registrado', $c->refresh()->acuse_estado);
        // deriva el expediente a quien debe actuar
        $dest = $this->usuario('funcionario', 3);
        $this->comoFuncionario($s)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $dest->id, 'motivo' => 'Preparar respuesta', 'accion' => 'responder'])->assertSessionHas('ok');
        $this->comoFuncionario($dest)->get('/app')->assertSee('Preparar y enviar la respuesta');
        $this->comoFuncionario($s)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'en_gestion'])->assertSessionHas('ok');
    }

    public function test_un_administrador_sin_la_condicion_de_superadmin_sigue_sin_operar(): void
    {
        [$e, $c] = $this->entrante();
        $this->comoFuncionario($this->usuario('admin'))->post("/app/comunicaciones/{$c->id}/acuse")->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $this->usuario('funcionario', 3)->id, 'motivo' => 'x'])->assertForbidden();
    }

    public function test_derivar_con_accion_y_plazo_y_el_funcionario_cambia_el_estado(): void
    {
        [$e, $c] = $this->entrante();
        $sec = $this->usuario('secretario');
        $dest = $this->usuario('funcionario', 3);
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $dest->id, 'motivo' => 'Informe técnico', 'accion' => 'informar', 'plazo' => now()->addDays(5)->format('Y-m-d')])->assertSessionHas('ok');
        $d = Derivacion::query()->firstOrFail();
        $this->assertSame(['informar', 'pendiente'], [$d->accion, $d->estado]);
        // el funcionario lo ve en su bandeja con la acción
        $this->comoFuncionario($dest)->get('/app')->assertOk()->assertSee('Acciones que le derivaron')->assertSee('Informar o emitir antecedentes')->assertSee($e->codigo);
        // y en el expediente puede actualizar el estado; otra persona no
        $this->comoFuncionario($dest)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('Actualizar estado');
        $this->comoFuncionario($this->usuario('funcionario', 1))->post("/app/expedientes/{$e->id}/derivaciones/{$d->id}/estado", ['estado' => 'cumplida', 'nota' => 'x'])->assertForbidden();
        $this->comoFuncionario($dest)->post("/app/expedientes/{$e->id}/derivaciones/{$d->id}/estado", ['estado' => 'en_proceso'])->assertSessionHas('ok');
        $this->assertSame('en_proceso', $d->refresh()->estado);
        $this->comoFuncionario($dest)->post("/app/expedientes/{$e->id}/derivaciones/{$d->id}/estado", ['estado' => 'cumplida'])->assertSessionHas('error'); // exige nota
        $this->comoFuncionario($dest)->post("/app/expedientes/{$e->id}/derivaciones/{$d->id}/estado", ['estado' => 'cumplida', 'nota' => 'Informe enviado por correo'])->assertSessionHas('ok');
        $d->refresh();
        $this->assertSame('cumplida', $d->estado);
        $this->assertNotNull($d->resuelto_en);
        $this->assertDatabaseHas('comentarios', ['expediente_id' => $e->id, 'usuario_id' => $dest->id]);
        $this->assertDatabaseHas('audit_log', ['accion' => 'expediente.derivacion_estado']);
        // una derivación cerrada no se vuelve a cambiar y deja de aparecer en la bandeja
        $this->comoFuncionario($dest)->post("/app/expedientes/{$e->id}/derivaciones/{$d->id}/estado", ['estado' => 'devuelta', 'nota' => 'xx'])->assertSessionHas('error');
        $this->comoFuncionario($dest)->get('/app')->assertDontSee('Acciones que le derivaron');
    }

    public function test_plazo_vencido_acciones_invalidas_y_derivaciones_antiguas(): void
    {
        [$e] = $this->entrante();
        $sec = $this->usuario('secretario');
        $dest = $this->usuario('funcionario', 3);
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $dest->id, 'motivo' => 'x1', 'accion' => 'hackear'])->assertSessionHasErrors('accion');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $dest->id, 'motivo' => 'x1', 'accion' => 'revisar', 'plazo' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('plazo');
        $d = Derivacion::query()->create(['expediente_id' => $e->id, 'de_usuario_id' => $sec->id, 'a_usuario_id' => $dest->id, 'motivo' => 'antigua', 'accion' => 'revisar', 'plazo' => now()->subDays(2), 'estado' => 'pendiente']);
        $this->assertTrue($d->vencida());
        $this->comoFuncionario($dest)->get('/app')->assertSee('Vencida');
        // sin acción informada (clientes antiguos) se guarda como «otra»
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $dest->id, 'motivo' => 'sin accion'])->assertSessionHas('ok');
        $this->assertSame('otra', Derivacion::query()->latest('id')->first()->accion);
        // derivación anterior (sin estado): no aparece como tarea
        Derivacion::query()->create(['expediente_id' => $e->id, 'de_usuario_id' => $sec->id, 'a_usuario_id' => $dest->id, 'motivo' => 'vieja']);
        $this->assertSame(0, Derivacion::query()->where('motivo', 'vieja')->whereIn('estado', ['pendiente', 'en_proceso'])->count());
    }

    public function test_estado_de_gestion_de_la_comunicacion_exige_acuse(): void
    {
        [$e, $c] = $this->entrante();
        $sec = $this->usuario('secretario');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'en_gestion'])->assertSessionHas('error');
        $this->assertSame('recibida', $c->refresh()->gestion);
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('ok');
        $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->assertSee('Estado de gestión')->assertSee('Guardar estado');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'borrar'])->assertSessionHasErrors('gestion');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'en_gestion', 'nota' => 'Se pidió informe'])->assertSessionHas('ok');
        $this->assertSame(['en_gestion', $sec->id], [$c->refresh()->gestion, (int) $c->gestion_por_id]);
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'en_gestion'])->assertSessionHas('error');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'respondida'])->assertSessionHas('ok');
        $this->assertDatabaseHas('audit_log', ['accion' => 'docdigital.gestion']);
        $this->assertDatabaseHas('comentarios', ['expediente_id' => $e->id, 'usuario_id' => $sec->id]);
        // quien no participa no puede cambiarlo
        $this->comoFuncionario($this->usuario('funcionario', 3))->post("/app/comunicaciones/{$c->id}/gestion", ['gestion' => 'archivada'])->assertForbidden();
        $this->assertSame(1, Expediente::query()->where('origen', 'docdigital')->count());
    }
}
