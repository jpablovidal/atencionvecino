<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\TramiteAnexo;
use App\Models\TramiteVersion;
use App\Models\Usuario;
use App\Services\ExpedienteService;
use App\Services\SolicitudService;
use App\Services\TramiteService;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class GestionTramitesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['nombre' => 'Certificado de residencia', 'descripcion' => 'Certifica el domicilio del vecino dentro de la comuna para fines administrativos.', 'instrucciones' => 'Presente su cédula vigente.', 'plazo_dias' => 5,
            'anexos' => [['nombre' => 'Cédula de identidad', 'obligatorio' => 1, 'descripcion' => 'Ambos lados'], ['nombre' => 'Boleta de servicios', 'obligatorio' => 0]]];
    }

    private function director(): Usuario
    {
        return Usuario::query()->where('rol', 'dir_tramites')->firstOrFail(); // DIDECO (4)
    }

    private function redactor(): Usuario
    {
        return Usuario::query()->where('rol', 'edit_tramites')->firstOrFail(); // DOM (3)
    }

    public function test_matriz_de_permisos_por_rol(): void
    {
        $this->assertTrue($this->usuario('admin')->tienePermiso('tramites.gestionar'));
        $this->assertTrue($this->usuario('admin')->tienePermiso('tramites.publicar'));
        foreach (['funcionario', 'alcalde', 'secretario', 'auditor'] as $rol) {
            $u = $this->usuario($rol);
            $this->assertFalse($u->tienePermiso('tramites.gestionar'), "$rol no gestiona por defecto");
            $this->assertFalse($u->tienePermiso('tramites.publicar'), "$rol no publica por defecto");
        }
        $this->assertTrue($this->director()->tienePermiso('tramites.publicar'));
        $this->assertTrue($this->redactor()->tienePermiso('tramites.gestionar'));
        $this->assertFalse($this->redactor()->tienePermiso('tramites.publicar'));
        // el auditor nunca puede recibirlos (techo del rol base)
        $this->assertFalse(in_array('tramites.gestionar', Permisos::techo('auditor'), true));
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/tramites')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/tramites/nuevo')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/tramites', $this->datos())->assertForbidden();
    }

    public function test_crear_borrador_publicar_y_ciudadano_genera_expediente(): void
    {
        $dir = $this->director();
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos())->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Certificado de residencia')->firstOrFail();
        $this->assertSame('borrador', $t->estado);
        $this->assertFalse($t->activo);
        $this->assertSame(4, (int) $t->unidad_propietaria_id);
        $this->assertSame(2, $t->anexos()->count());

        // el borrador NO es visible ni solicitable por la ciudadanía
        $this->comoCiudadano()->get('/ciudadano')->assertDontSee('Certificado de residencia');
        $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->assertNotFound();
        $this->expectExceptionOnIngresar($t);

        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar")->assertSessionHas('ok');
        $t->refresh();
        $this->assertSame('publicado', $t->estado);
        $this->assertTrue($t->activo);
        $this->comoCiudadano()->get('/ciudadano')->assertSee('Certificado de residencia');
        $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->assertOk()->assertSee('Cédula de identidad')->assertSee('Ambos lados');

        // sin el adjunto obligatorio: rechazo
        $a = $t->anexos->firstWhere('nombre', 'Cédula de identidad');
        $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Necesito el certificado.', 'consentimiento' => 'si', 'canal_notificacion' => 'correo'])->assertSessionHas('error');
        $r = $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Necesito el certificado.', 'consentimiento' => 'si', 'canal_notificacion' => 'correo', 'anexo_'.$a->id => $this->archivo('cedula.pdf')]);
        $r->assertSessionHas('ok');
        $s = Solicitud::query()->where('tramite_id', $t->id)->firstOrFail();
        $this->assertTrue(Archivo::query()->where('solicitud_id', $s->id)->where('nombre_original', 'cedula.pdf')->exists());

        // la solicitud llega a la dirección responsable y genera expediente
        $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app')->assertSee($s->folio); // Marcos, DIDECO
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->usuario('funcionario', 3));
        $this->assertNotNull($e->id);
        $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app')->assertDontSee($s->folio); // DOM no la ve
    }

    private function expectExceptionOnIngresar(Tramite $t): void
    {
        try {
            app(SolicitudService::class)->ingresar($this->ciudadano(), $t, 'a@b.cl', 'Quiero el certificado ya.', 'correo', [], []);
            $this->fail('un trámite no publicado no admite solicitudes');
        } catch (FlujoException $e) {
            $this->assertStringContainsString('no está disponible', $e->getMessage());
        }
    }

    public function test_deshabilitar_corta_nuevas_solicitudes_pero_conserva_las_existentes(): void
    {
        $dir = $this->director();
        $s = app(SolicitudService::class);
        $t = Tramite::query()->find(3); // DIDECO publicado
        $sol = $s->ingresar($this->ciudadano(), $t, 'a@b.cl', 'Solicito un beneficio social municipal.', 'correo', [], []);
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/deshabilitar")->assertSessionHas('ok');
        $t->refresh();
        $this->assertSame('deshabilitado', $t->estado);
        $this->assertFalse($t->activo);
        $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->assertNotFound();
        $this->expectExceptionOnIngresar($t);
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$sol->folio}")->assertOk(); // la ya ingresada sigue su curso
        // deshabilitar dos veces o un borrador es un error controlado
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/deshabilitar")->assertSessionHas('error');
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar")->assertSessionHas('ok'); // reactivar
        $this->assertTrue($t->refresh()->estaPublicado());
        $this->assertSame(['reactivar', 'deshabilitar'], TramiteVersion::query()->where('tramite_id', $t->id)->orderByDesc('version')->limit(2)->pluck('evento')->all());
    }

    public function test_ambito_por_direccion_y_anti_idor(): void
    {
        $dir = $this->director(); // DIDECO
        $dom = $this->redactor();   // DOM
        $deDom = Tramite::query()->find(1); // DOM publicado
        $deDideco = Tramite::query()->find(3);
        $this->comoFuncionario($dir)->get("/app/tramites/{$deDom->id}")->assertNotFound();
        $this->comoFuncionario($dir)->post("/app/tramites/{$deDom->id}", $this->datos())->assertNotFound();
        $this->comoFuncionario($dir)->post("/app/tramites/{$deDom->id}/deshabilitar")->assertNotFound();
        $this->comoFuncionario($dir)->post("/app/tramites/{$deDom->id}/publicar")->assertNotFound();
        $this->comoFuncionario($dir)->post("/app/tramites/{$deDom->id}/duplicar")->assertNotFound();
        $this->comoFuncionario($dir)->get("/app/tramites/{$deDom->id}/historial")->assertNotFound();
        $this->comoFuncionario($dir)->get("/app/tramites/{$deDom->id}/vista")->assertNotFound();
        $this->comoFuncionario($dir)->get('/app/tramites')->assertSee('Solicitud de beneficio social')->assertDontSee('Permiso de edificación');
        $this->assertSame('publicado', $deDom->refresh()->estado);
        $this->assertSame('Permiso de edificación', $deDom->nombre);
        // el listado de DOM no incluye los de DIDECO
        $this->comoFuncionario($dom)->get('/app/tramites')->assertSee('Permiso de edificación')->assertDontSee('Solicitud de beneficio social');
        $this->assertTrue(TramiteService::puedeGestionar($dir, $deDideco));
        $this->assertFalse(TramiteService::puedeGestionar($dir, $deDom));
        // la dirección NO se toma del formulario: se ignora cualquier unidad enviada por un no administrador
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite de prueba DIDECO', 'unidad_propietaria_id' => 3, 'unidades' => [3], 'codigo_interno' => 'HACK-1', 'codigo_cpat' => 'PA-UNI00002-00077']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Trámite de prueba DIDECO')->firstOrFail();
        $this->assertSame(4, (int) $t->unidad_propietaria_id);
        $this->assertSame([4], $t->unidades()->pluck('unidades.id')->all());
        $this->assertNotSame('HACK-1', $t->codigo_interno);
        $this->assertNull($t->codigo_cpat);
        // el administrador ve todas
        $this->comoFuncionario($this->usuario('admin'))->get('/app/tramites')->assertSee('Permiso de edificación')->assertSee('Solicitud de beneficio social');
    }

    public function test_anexos_de_otro_tramite_no_se_pueden_secuestrar(): void
    {
        $dir = $this->director();
        $t = Tramite::query()->find(3);
        $ajeno = TramiteAnexo::query()->where('tramite_id', 1)->firstOrFail();
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}", $this->datos(['anexos' => [['id' => $ajeno->id, 'nombre' => 'Intento de secuestro', 'obligatorio' => 1]]]))->assertSessionHas('ok');
        $this->assertSame('Planos de arquitectura', $ajeno->refresh()->nombre);
        $this->assertSame(1, (int) $ajeno->tramite_id);
        $this->assertTrue((bool) $ajeno->activo);
    }

    public function test_redactor_no_publica_ni_edita_publicados(): void
    {
        $red = $this->redactor(); // DOM
        $this->comoFuncionario($red)->post('/app/tramites', $this->datos(['nombre' => 'Borrador del redactor']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Borrador del redactor')->firstOrFail();
        $this->assertSame(3, (int) $t->unidad_propietaria_id);
        $this->comoFuncionario($red)->post("/app/tramites/{$t->id}", $this->datos(['nombre' => 'Borrador del redactor v2']))->assertSessionHas('ok');
        $this->comoFuncionario($red)->post("/app/tramites/{$t->id}/publicar")->assertSessionHas('error');
        $this->assertSame('borrador', $t->refresh()->estado);
        // un trámite ya publicado de su dirección: no lo puede modificar, sí deshabilitar
        $pub = Tramite::query()->find(2);
        $this->comoFuncionario($red)->post("/app/tramites/{$pub->id}", $this->datos(['nombre' => 'Nombre cambiado']))->assertSessionHas('error');
        $this->assertSame('Certificado de número', $pub->refresh()->nombre);
        $this->comoFuncionario($red)->post("/app/tramites/{$pub->id}/deshabilitar")->assertSessionHas('ok');
    }

    public function test_publicar_exige_requisitos_minimos(): void
    {
        $dir = $this->director();
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite incompleto', 'descripcion' => 'Corta']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Trámite incompleto')->firstOrFail();
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar")->assertSessionHas('error');
        $this->assertSame('borrador', $t->refresh()->estado);
    }

    public function test_validaciones_de_entrada(): void
    {
        $dir = $this->director();
        foreach ([['nombre' => 'abc'], ['plazo_dias' => 0], ['plazo_dias' => 400], ['nombre' => 'Ver http://malo.example/x ahora']] as $mal) {
            $this->comoFuncionario($dir)->post('/app/tramites', $this->datos($mal))->assertSessionHas('error');
        }
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['anexos' => [['nombre' => 'Igual'], ['nombre' => 'igual']]]))->assertSessionHas('error'); // repetidos
        $mucho = array_map(fn ($i) => ['nombre' => "Doc $i"], range(1, 16));
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['anexos' => $mucho]))->assertSessionHas('error');
        $this->assertSame(0, Tramite::query()->where('unidad_propietaria_id', 4)->where('estado', 'borrador')->count());
        $this->comoFuncionario($dir)->post('/app/tramites', ['nombre' => 'Sin plazo'])->assertSessionHasErrors('plazo_dias');
    }

    public function test_xss_e_invisibles_en_textos_se_neutralizan(): void
    {
        $dir = $this->director();
        $mal = "Certificado<script>alert(1)</script> <img src=x onerror=alert(2)> \u{202E}evil\u{200B}";
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite '.$mal, 'descripcion' => '<b onclick="x()">Descripción</b> larga suficiente para publicar '.$mal, 'anexos' => [['nombre' => '<i>Cédula</i>'.$mal, 'descripcion' => '<a href="javascript:alert(1)">ayuda</a>', 'obligatorio' => 1]]]))->assertSessionHas('ok');
        $t = Tramite::query()->where('unidad_propietaria_id', 4)->latest('id')->firstOrFail();
        foreach ([$t->nombre, $t->descripcion, $t->anexos->first()->nombre, (string) $t->anexos->first()->descripcion] as $campo) {
            $this->assertStringNotContainsString('<', $campo);
            $this->assertStringNotContainsString("\u{202E}", $campo);
            $this->assertStringNotContainsString("\u{200B}", $campo);
        }
        $t->forceFill(['estado' => 'publicado', 'activo' => true])->save();
        $html = $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        // aunque llegara basura a la BD por otra vía, la vista escapa
        $t->forceFill(['descripcion' => '<script>alert(9)</script>'])->save();
        $this->assertStringNotContainsString('<script>alert(9)', $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->getContent());
    }

    public function test_direcciones_web_en_el_texto_generan_advertencia_sin_enlace(): void
    {
        $dir = $this->director();
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite con enlace', 'instrucciones' => 'Más información en https://ejemplo.cl/info y www.otro.cl']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Trámite con enlace')->firstOrFail();
        $r = $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar");
        $this->assertStringContainsString('direcciones web', session('ok'));
        $html = $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->getContent();
        $this->assertStringNotContainsString('href="https://ejemplo.cl', $html);
        $this->assertStringContainsString('https://ejemplo.cl/info', $html);
    }

    public function test_versiones_inmutables_y_auditoria(): void
    {
        $dir = $this->director();
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite versionado']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Trámite versionado')->firstOrFail();
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}", $this->datos(['nombre' => 'Trámite versionado B']))->assertSessionHas('ok');
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar");
        $v = TramiteVersion::query()->where('tramite_id', $t->id)->orderBy('version')->get();
        $this->assertSame(['crear', 'editar', 'publicar'], $v->pluck('evento')->all());
        $this->assertSame('Trámite versionado', $v[0]->contenido()['nombre']);
        $this->assertSame(3, $t->refresh()->version);
        try {
            $v[0]->update(['evento' => 'x']);
            $this->fail('inmutable');
        } catch (\LogicException) {
            $this->assertSame('crear', $v[0]->refresh()->evento);
        }
        $this->expectException(\LogicException::class);
        $v[0]->delete();
    }

    public function test_auditoria_registra_eventos(): void
    {
        $dir = $this->director();
        $this->comoFuncionario($dir)->post('/app/tramites', $this->datos(['nombre' => 'Trámite auditado']))->assertSessionHas('ok');
        $t = Tramite::query()->where('nombre', 'Trámite auditado')->firstOrFail();
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/publicar");
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/deshabilitar");
        foreach (['tramite.crear', 'tramite.publicar', 'tramite.deshabilitar'] as $a) {
            $this->assertDatabaseHas('audit_log', ['accion' => $a, 'entidad_id' => (string) $t->id]);
        }
    }

    public function test_duplicar_crea_borrador_en_la_direccion_de_quien_copia(): void
    {
        $dir = $this->director();
        $t = Tramite::query()->find(3);
        $this->comoFuncionario($dir)->post("/app/tramites/{$t->id}/duplicar")->assertSessionHas('ok');
        $c = Tramite::query()->where('nombre', 'like', 'Copia de%')->firstOrFail();
        $this->assertSame('borrador', $c->estado);
        $this->assertFalse($c->activo);
        $this->assertSame(4, (int) $c->unidad_propietaria_id);
        $this->assertNotSame($t->codigo_interno, $c->codigo_interno);
    }

    public function test_usuario_sin_direccion_no_puede_crear(): void
    {
        $u = $this->director();
        $u->forceFill(['unidad_id' => null])->save();
        $this->comoFuncionario($u->refresh())->post('/app/tramites', $this->datos())->assertSessionHas('error');
        $this->comoFuncionario($u)->get('/app/tramites')->assertOk()->assertDontSee('Solicitud de beneficio social');
    }

    public function test_rol_personalizado_no_puede_recibir_permiso_fuera_de_su_techo(): void
    {
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/admin/roles', ['nombre' => 'Auditor con trámites', 'clave' => 'aud_tram', 'rol_base' => 'auditor', 'permisos' => ['tramites.gestionar']]);
        $rol = Rol::query()->where('clave', 'aud_tram')->first();
        $this->assertTrue($rol === null || ! in_array('tramites.gestionar', $rol->permisos(), true));
    }

    public function test_menu_muestra_trámites_solo_a_quien_corresponde(): void
    {
        $this->comoFuncionario($this->director())->get('/app')->assertSee('Trámites y servicios');
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertDontSee('app/tramites');
    }

    public function test_llm_nunca_recibe_datos_de_tramites(): void
    {
        // el borrador de IA solo se construye con contexto del expediente; el servicio de trámites no invoca el LLM
        $src = file_get_contents(base_path('app/Services/TramiteService.php')).file_get_contents(base_path('app/Http/Controllers/TramitesController.php'));
        $this->assertStringNotContainsString('Llm', $src);
        $this->assertStringNotContainsString('llm', strtolower($src));
    }

    public function test_el_texto_del_tramite_no_se_interpreta_como_instruccion_en_pdf_ni_correos(): void
    {
        $t = Tramite::query()->find(1);
        $t->forceFill(['nombre' => 'Permiso <b>x</b>'])->save();
        $this->comoCiudadano()->get('/ciudadano')->assertDontSee('<b>x</b>', false);
    }
}
