<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Expediente;
use App\Services\AccesoRestringidoService;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class RestringidosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Expediente $e;

    private Archivo $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $dom = $this->usuario('funcionario', 1);
        $s = $this->solicitudDOM();
        $this->e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $this->a = Archivo::query()->where('expediente_id', $this->e->id)->where('ambito', 'anexo')->firstOrFail();
    }

    private function svc(): AccesoRestringidoService
    {
        return app(AccesoRestringidoService::class);
    }

    private function dueno()
    {
        return $this->usuario('funcionario', 1);
    }

    public function test_sin_restriccion_el_archivo_se_descarga_y_no_se_registra_bitacora(): void
    {
        $this->comoFuncionario($this->dueno())->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertOk();
        $this->assertSame(0, AuditLog::query()->where('accion', 'like', 'restringido.%')->count());
    }

    public function test_motivo_obligatorio_y_solo_autorizados_cambian_la_restriccion(): void
    {
        $d = $this->dueno();
        $this->comoFuncionario($d)->post("/app/restringidos/expedientes/{$this->e->id}/restringir", ['motivo' => 'corto'])->assertSessionHas('error');
        $this->assertFalse($this->e->fresh()->restringido);
        $this->comoFuncionario($d)->post("/app/restringidos/expedientes/{$this->e->id}/restringir", ['motivo' => 'Contiene datos personales sensibles del solicitante'])->assertSessionHas('ok');
        $this->assertTrue($this->e->fresh()->restringido);
        $this->expectException(FlujoException::class);
        $this->svc()->liberar($this->usuario('secretario'), $this->e->fresh());
    }

    public function test_no_autorizados_no_acceden_y_queda_el_intento_en_la_bitacora(): void
    {
        $d = $this->dueno();
        $this->svc()->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante', $this->a);
        $otro = $this->usuario('auditor');
        $this->svc()->autorizar($d, $this->e->fresh(), $otro->id, 'Necesita conocerlo para visar la resolución', null, $this->a->fresh());
        $this->assertTrue($this->svc()->autorizado($otro, $this->e->fresh(), $this->a->fresh()));
        $this->assertFalse($this->svc()->autorizado($this->usuario('admin'), $this->e->fresh(), $this->a->fresh()), 'el administrador nunca accede al contenido');

        $this->comoFuncionario($d)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertOk();
        $this->comoFuncionario($otro)->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertOk();
        $this->assertSame(2, AuditLog::query()->where('accion', 'restringido.acceso')->count());

        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertForbidden();
    }

    public function test_un_auditor_sin_autorizacion_es_denegado_y_queda_registrado(): void
    {
        $this->svc()->restringir($this->dueno(), $this->e, 'Contiene datos personales sensibles del solicitante', $this->a);
        $this->comoFuncionario($this->usuario('auditor'))->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertForbidden();
        $this->assertSame(1, AuditLog::query()->where('accion', 'restringido.intento')->count());
    }

    public function test_el_administrador_no_puede_autorizarse_ni_ser_autorizado(): void
    {
        $d = $this->dueno();
        $this->svc()->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante');
        $this->expectException(FlujoException::class);
        $this->svc()->autorizar($d, $this->e->fresh(), $this->usuario('admin')->id, 'Intento de dar acceso a administración', null);
    }

    public function test_vencimiento_maximo_un_anio_y_revocacion(): void
    {
        $d = $this->dueno();
        $otro = $this->usuario('secretario');
        $this->svc()->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante');
        $this->assertThrows(fn () => $this->svc()->autorizar($d, $this->e->fresh(), $otro->id, 'Necesita conocerlo para visar', now()->addYears(2)->format('Y-m-d')), FlujoException::class);
        $r = $this->svc()->autorizar($d, $this->e->fresh(), $otro->id, 'Necesita conocerlo para visar', now()->addDays(10)->format('Y-m-d'));
        $this->assertTrue($this->svc()->autorizado($otro, $this->e->fresh()));
        $this->svc()->revocar($d, $r);
        $this->assertFalse($this->svc()->autorizado($otro->fresh(), $this->e->fresh()));
        $this->assertSame(0, AccesoRestringido::query()->vigentes()->where('usuario_id', $otro->id)->count());
    }

    public function test_la_pagina_del_expediente_enmascara_el_archivo_y_muestra_la_gestion(): void
    {
        $d = $this->dueno();
        $this->svc()->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante', $this->a);
        $r = $this->comoFuncionario($d)->get("/app/expedientes/{$this->e->id}")->assertOk();
        $r->assertSee('Acceso restringido');
        $r->assertSee('Autorizar a una persona');
    }

    public function test_bitacora_de_accesos_solo_para_auditoria_y_administracion(): void
    {
        $d = $this->dueno();
        $this->svc()->restringir($d, $this->e, 'Contiene datos personales sensibles del solicitante', $this->a);
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get("/app/expedientes/{$this->e->id}/archivos/{$this->a->id}")->assertForbidden();
        auth()->forgetGuards();
        $r = $this->comoFuncionario($this->usuario('admin'))->get('/app/restringidos')->assertOk();
        $r->assertSee('Denegado');
        $r->assertDontSee('REMOTE_ADDR');
        foreach (['funcionario', 'secretario', 'alcalde'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->get('/app/restringidos')->assertForbidden();
        }
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/restringidos?resultado=denegado')->assertOk();
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('accion', 'restringido.auditar_ver')->count());
    }

    public function test_los_forbidden_de_gestion_para_roles_sin_permiso(): void
    {
        foreach (['admin', 'auditor', 'autoridad', 'oirs'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->post("/app/restringidos/expedientes/{$this->e->id}/restringir", ['motivo' => 'Motivo suficientemente largo'])->assertForbidden();
        }
        $this->assertFalse($this->e->fresh()->restringido);
    }
}
