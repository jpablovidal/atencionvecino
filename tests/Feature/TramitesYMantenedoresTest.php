<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Devolucion;
use App\Models\Plantilla;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class TramitesYMantenedoresTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_devolucion_con_plazo_respuesta_y_vencimiento(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $s = $this->solicitudDOM();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        foreach ([0, 91] as $dias) {
            $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/devolver", ['observacion' => 'Falta firma', 'plazo_dias' => $dias])->assertSessionHasErrors('plazo_dias');
        }
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/devolver", ['observacion' => 'Falta firma del propietario', 'plazo_dias' => 10])->assertSessionHas('ok');
        $this->assertSame('devuelta', $s->refresh()->estado);
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->assertSee('Falta firma del propietario')->assertSee('Plazo para responder');
        $this->comoCiudadano()->post("/ciudadano/solicitudes/{$s->folio}/responder", ['respuesta' => 'Adjunto lo solicitado', 'archivos' => [$this->archivo('nuevo.pdf')]])->assertSessionHas('ok');
        $this->assertSame('en_tramite', $s->refresh()->estado);
        $this->assertSame(1, Archivo::query()->where('ambito', 'respuesta')->count());
        // devolución vencida: no se puede responder
        app(SolicitudService::class)->devolver($s, $dom, 'Otra observación', 1);
        Devolucion::query()->latest('id')->first()->forceFill(['vence_en' => now()->subDay()])->save();
        $this->comoCiudadano()->post("/ciudadano/solicitudes/{$s->folio}/responder", ['respuesta' => 'Tarde'])->assertSessionHas('error');
    }

    public function test_cierre_reapertura_con_motivo_y_exportacion_json(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/cerrar")->assertSessionHas('ok');
        $this->assertSame('cerrado', $e->refresh()->estado);
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/reabrir", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/reabrir", ['motivo' => 'Nuevos antecedentes'])->assertSessionHas('ok');
        $this->assertSame('abierto', $e->refresh()->estado);
        $r = $this->comoFuncionario($dom)->get("/app/expedientes/{$e->id}/exportar")->assertOk();
        $j = json_decode($r->getContent(), true);
        foreach (['identificacion', 'descripcion', 'temporalidad', 'caracterizacion', 'relaciones', 'indice', 'poderes', 'seguridad', 'procedimiento', 'version'] as $k) {
            $this->assertArrayHasKey($k, $j, "MGDEE: $k");
        }
        $this->assertSame('PA-UNI00002-00001', $j['procedimiento']['codigo_cpat']);
        $this->assertNotEmpty($j['indice']);
    }

    public function test_cpat_validado_por_patron_y_multiples_unidades(): void
    {
        $admin = $this->usuario('admin');
        $base = ['codigo_interno' => 'X-1', 'nombre' => 'Trámite X', 'plazo_dias' => 10, 'unidades' => [1, 3], 'anexos_txt' => "Cédula|1\nFoto|0"];
        foreach (['PA-UNI0002-00001', 'pa-uni00002-00001', 'XXXXXXXXXXXXXXXX', 'PA-UNI00002-0001'] as $malo) {
            $this->comoFuncionario($admin)->post('/app/admin/tramites', $base + ['codigo_cpat' => $malo])->assertSessionHasErrors('codigo_cpat');
        }
        $this->comoFuncionario($admin)->post('/app/admin/tramites', $base + ['codigo_cpat' => 'PA-UNI00002-00009'])->assertRedirect('/app/admin/tramites');
        $t = Tramite::query()->where('codigo_interno', 'X-1')->first();
        $this->assertSame([1, 3], $t->unidades()->pluck('unidades.id')->all());
        $this->assertSame(2, $t->anexos()->count());
        $this->assertFalse((bool) $t->anexos->firstWhere('nombre', 'Foto')->obligatorio);
        $this->comoFuncionario($admin)->post('/app/admin/tramites', array_merge($base, ['codigo_interno' => 'X-2', 'codigo_cpat' => null, 'unidades' => []]))->assertSessionHasErrors('unidades');
        // la solicitud llega a la bandeja de TODAS las unidades responsables
        $t2 = Tramite::query()->find(4); // ALC + SECMUN
        $s = app(SolicitudService::class)->ingresar($this->ciudadano(), $t2, 'a@b.cl', 'Quiero una audiencia con el alcalde.', 'presencial', [], []);
        $this->comoFuncionario($this->usuario('alcalde'))->get('/app')->assertSee($s->folio);
        $this->comoFuncionario($this->usuario('secretario'))->get('/app')->assertSee($s->folio);
        $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app')->assertDontSee($s->folio);
    }

    public function test_sin_borrado_fisico_solo_desactivar(): void
    {
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/admin/usuarios/4/activo')->assertRedirect();
        $this->assertFalse((bool) Usuario::query()->find(4)->activo);
        $this->assertDatabaseHas('usuarios', ['id' => 4]);
        $this->comoFuncionario($admin)->delete('/app/admin/usuarios/4')->assertStatus(405);
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.activo']);
    }

    public function test_usuario_con_run_valido_y_unico_y_roles_validos(): void
    {
        $admin = $this->usuario('admin');
        $u = ['nombres' => 'N', 'apellidos' => 'A', 'correo' => 'n@a.cl', 'rol' => 'funcionario', 'unidad_id' => 3];
        $this->comoFuncionario($admin)->post('/app/admin/usuarios', $u + ['run' => '11.111.111-9'])->assertStatus(422);
        $this->comoFuncionario($admin)->post('/app/admin/usuarios', array_merge($u, ['run' => '12.345.678-5', 'rol' => 'superadmin']))->assertSessionHasErrors('rol');
        $this->comoFuncionario($admin)->post('/app/admin/usuarios', $u + ['run' => '12.345.678-5', 'puede_firmar' => 1])->assertSessionHasNoErrors()->assertRedirect('/app/admin/usuarios');
        $this->comoFuncionario($admin)->post('/app/admin/usuarios', $u + ['run' => '12345678-5'])->assertSessionHasErrors('run');
        $this->assertDatabaseHas('usuarios', ['run' => '12345678-5', 'puede_firmar' => 1]);
        // admin y auditor nunca firman
        $this->comoFuncionario($admin)->post('/app/admin/usuarios', array_merge($u, ['run' => '9.999.999-3', 'rol' => 'auditor', 'puede_firmar' => 1]));
        $this->assertDatabaseHas('usuarios', ['rol' => 'auditor', 'puede_firmar' => 0]);
    }

    public function test_plantillas_se_sanitizan_en_servidor(): void
    {
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/plantillas', ['tipo_documento_id' => 3, 'nombre' => 'Mala', 'contenido_html' => '<p>Hola {{fecha}}</p><script>alert(1)</script><img src=x onerror=alert(1)>'])->assertRedirect();
        $p = Plantilla::query()->where('nombre', 'Mala')->first();
        $this->assertStringNotContainsString('script', $p->contenido_html);
        $this->assertStringNotContainsString('onerror', $p->contenido_html);
        $this->assertStringContainsString('{{fecha}}', $p->contenido_html);
    }

    public function test_campos_de_combinacion_se_escapan(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $s = $this->solicitudDOM();
        $s->forceFill(['nombres' => '<b onmouseover=alert(1)>Eve</b>'])->save();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $doc = app(DocumentoService::class)->crear($e, TipoDocumento::query()->first(), Plantilla::query()->first(), 'T', $dom);
        $this->assertStringNotContainsString('<b onmouseover', $doc->html);
        $this->assertStringContainsString('&lt;b', $doc->html);
        $this->assertStringContainsString($e->codigo, $doc->html);
    }

    public function test_listado_vigentes_con_enlace_publico_y_filtros(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $d = $this->documentoVigente($e, $dom);
        $d->forceFill(['nivel_acceso' => 'publico', 'titulo' => 'Oficio de prueba especial'])->save();
        $this->comoFuncionario($dom)->get('/app/vigentes')->assertSee($d->codigo_validacion)->assertSee('Oficio de prueba especial');
        $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app/vigentes')->assertDontSee($d->codigo_validacion); // otra unidad
        $this->get('/publico?q=especial')->assertSee('Oficio de prueba especial');
        $this->get('/publico?q=inexistente')->assertDontSee('Oficio de prueba especial');
        $this->get('/publico?tipo=3&anio='.now()->year)->assertSee('Oficio de prueba especial');
        $this->get('/publico?tipo=1')->assertDontSee('Oficio de prueba especial');
    }

    public function test_pagina_de_validacion_publica_no_expone_datos_de_expediente(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $s = $this->solicitudDOM();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $d = $this->documentoVigente($e, $dom);
        $html = $this->get('/validar/'.$d->codigo_validacion)->getContent();
        $this->assertStringNotContainsString($s->run, $html);
        $this->assertStringNotContainsString($s->folio, $html);
        $this->assertStringNotContainsString($e->codigo, $html);
        $this->get('/validar/SDD-ZZZZ-ZZZZ-ZZZZ')->assertSee('no corresponde');
        $this->get('/validar/../../etc/passwd')->assertNotFound();
        // comprobante: verificación pública con el código
        $c = SolicitudService::codigoComprobante($s->folio, Archivo::query()->where('ambito', 'anexo')->pluck('sha256')->all());
        $this->get("/validar-comprobante/{$s->folio}/$c")->assertSee('auténtico');
        $this->get("/validar-comprobante/{$s->folio}/CMP-AAAA-BBBB-CCCC")->assertSee('No se pudo verificar');
    }
}
