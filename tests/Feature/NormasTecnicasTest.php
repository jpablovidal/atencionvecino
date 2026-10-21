<?php

namespace Tests\Feature;

use App\Console\Commands\NormasDocumento;
use App\Models\AuditLog;
use App\Models\CalidadIniciativa;
use App\Models\CalidadPlataforma;
use App\Models\ConfigSitio;
use App\Models\Solicitud;
use App\Services\Admin\CumplimientoService;
use App\Services\ExpedienteService;
use App\Support\NormasTecnicas;
use App\Support\ProductionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Normas técnicas (Decretos 8 a 12): matriz, cumplimiento normativo, UTC, PISEE, consentimiento y accesos sensibles. */
class NormasTecnicasTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_la_matriz_es_consistente_y_el_documento_esta_al_dia(): void
    {
        $dec = NormasTecnicas::decretos();
        $this->assertSame([7, 8, 9, 10, 11, 12], array_keys($dec));
        $this->assertFalse($dec[7]['leido']);
        foreach ([8, 9, 10, 11, 12] as $n) {
            $this->assertTrue($dec[$n]['leido']);
        }
        $this->assertStringContainsString('Interoperabilidad', $dec[12]['titulo']);
        $this->assertStringContainsString('876', $dec[12]['version']);
        foreach (NormasTecnicas::matriz() as $f) {
            $this->assertArrayHasKey($f['decreto'], $dec);
            $this->assertArrayHasKey($f['estado'], NormasTecnicas::ESTADOS);
            $this->assertNotSame('', trim($f['requisito'].$f['sistema']));
        }
        $this->assertSame(count(NormasTecnicas::matriz()), array_sum(NormasTecnicas::resumen()));
        $this->assertSame(NormasDocumento::markdown(), (string) file_get_contents(base_path('docs/NORMAS_TECNICAS.md')), 'ejecute php artisan normas:documento');
        $this->artisan('normas:documento', ['--comprobar' => true])->assertSuccessful();
    }

    public function test_el_aviso_de_pisee_cuenta_los_dias_y_avisa_cuando_termina(): void
    {
        $antes = NormasTecnicas::avisoPisee(Carbon::parse('2026-10-21', 'America/Santiago'));
        $this->assertStringContainsString('(en 71 días)', $antes);
        $despues = NormasTecnicas::avisoPisee(Carbon::parse('2027-01-02', 'America/Santiago'));
        $this->assertStringContainsString('dejó de funcionar el 31-12-2026', $despues);
        config(['sgd.pisee.mode' => 'api']);
        $this->assertTrue(collect(ProductionGuard::advertencias())->contains(fn ($a) => str_contains($a, 'PISEE')));
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/api/pisee')->assertOk()->assertSee('Fin de PISEE', false);
    }

    public function test_pantalla_de_cumplimiento_con_matriz_filtros_y_permisos(): void
    {
        $admin = $this->comoFuncionario($this->usuario('admin'));
        $r = $admin->get('/app/admin/cumplimiento')->assertOk();
        $r->assertSee('Cumplimiento normativo')->assertSee('Norma Técnica de Notificaciones')->assertSee('Norma Técnica de Autenticación')->assertSee('Norma Técnica de Interoperabilidad')->assertSee('texto no entregado');
        $r->assertSee('31-12-2026')->assertSee('Matriz de cumplimiento por artículo');
        $admin->get('/app/admin/cumplimiento?estado=pendiente')->assertOk()->assertSee('Clave Tributaria')->assertDontSee('Domicilio Digital Único');
        $admin->get('/app/admin/cumplimiento?decreto=8')->assertOk()->assertSee('Domicilio Digital Único')->assertDontSee('Política de Gestión Documental (objetivo');
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/admin/cumplimiento')->assertOk()->assertDontSee('Guardar política');
        $this->comoFuncionario($this->usuario('auditor'))->post('/app/admin/cumplimiento/politica', ['decreto' => 'x', 'fecha_aprobacion' => '2026-01-01', 'revision_anios' => 2])->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/cumplimiento')->assertForbidden();
    }

    public function test_politica_de_gestion_documental_y_su_revision(): void
    {
        $svc = app(CumplimientoService::class);
        $this->assertSame('sin_registro', $svc->estadoPolitica()['estado']);
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/admin/cumplimiento/politica', ['decreto' => 'Decreto Alcaldicio 1/2026', 'fecha_aprobacion' => '2026-01-10', 'revision_anios' => 2, 'responsable' => 'DPO'])->assertSessionHas('ok');
        $e = $svc->estadoPolitica(Carbon::parse('2026-10-21', 'America/Santiago'));
        $this->assertSame(['vigente', '2028-01-10'], [$e['estado'], $e['proxima']]);
        $this->assertSame('por_revisar', $svc->estadoPolitica(Carbon::parse('2027-12-01', 'America/Santiago'))['estado']);
        $this->assertSame('vencida', $svc->estadoPolitica(Carbon::parse('2028-02-01', 'America/Santiago'))['estado']);
        $this->comoFuncionario($admin)->post('/app/admin/cumplimiento/politica', ['decreto' => 'x', 'fecha_aprobacion' => '2026-01-10', 'revision_anios' => 5])->assertSessionHasErrors('revision_anios');
        $this->comoFuncionario($admin)->post('/app/admin/cumplimiento/politica', ['decreto' => 'x', 'fecha_aprobacion' => now()->addDay()->format('Y-m-d'), 'revision_anios' => 1])->assertSessionHasErrors('fecha_aprobacion');
        $this->assertDatabaseHas('audit_log', ['accion' => 'cumplimiento.politica']);
    }

    public function test_coordinador_de_transformacion_digital_viene_del_decreto_2372_y_se_puede_cambiar(): void
    {
        $svc = app(CumplimientoService::class);
        $this->assertSame('Juan Pablo Vidal Araya', $svc->coordinador()['titular']);
        $this->assertStringContainsString('2372', $svc->coordinador()['decreto']);
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/cumplimiento/coordinador', ['titular' => 'Otra Persona', 'subrogante' => 'Sub', 'decreto' => 'Decreto 9', 'fecha' => '2026-09-01', 'comunicado_en' => '2026-09-05'])->assertSessionHas('ok');
        $this->assertSame(['Otra Persona', '2026-09-05'], [$svc->coordinador()['titular'], $svc->coordinador()['comunicado_en']]);
        $this->assertNotNull(ConfigSitio::query()->where('clave', CumplimientoService::CLAVE_COORDINADOR)->first());
    }

    public function test_catalogo_de_plataformas_y_plan_de_mejora_continua(): void
    {
        $admin = $this->comoFuncionario($this->usuario('admin'));
        $admin->get('/app/admin/cumplimiento')->assertOk()->assertSee('SGD — Sistema de Atención y Gestión Documental')->assertSee('ClaveÚnica')->assertSee('DocDigital');
        $this->assertGreaterThanOrEqual(8, CalidadPlataforma::query()->count());
        $this->assertSame(0, app(CumplimientoService::class)->sembrarCatalogo(), 'no se vuelve a sembrar');
        $sgd = CalidadPlataforma::query()->where('priorizada', true)->firstOrFail();
        $admin->post('/app/admin/cumplimiento/plataformas', ['nombre' => 'Intranet', 'tipo' => 'propia', 'linea_base' => 'Versión 2024', 'linea_base_fecha' => '2026-01-01', 'priorizada' => '1'])->assertSessionHas('ok');
        $this->assertTrue(CalidadPlataforma::query()->where('nombre', 'Intranet')->value('priorizada'));
        $admin->post('/app/admin/cumplimiento/plataformas', ['nombre' => 'X', 'tipo' => 'rara'])->assertSessionHasErrors(['nombre', 'tipo']);
        // iniciativas y regla de las 4 subcaracterísticas
        foreach (['Disponibilidad', 'Accesibilidad', 'Seguridad', 'Usabilidad'] as $i => $sub) {
            $this->assertFalse(app(CumplimientoService::class)->subcaracteristicas()['cumple']);
            $admin->post('/app/admin/cumplimiento/iniciativas', ['plataforma_id' => $sgd->id, 'descripcion' => "Mejorar $sub del SGD", 'etapa' => 1, 'estado' => 'planificada', 'subcaracteristica' => $sub, 'metrica' => 'Indicador '.$sub,
                'umbral_exitoso' => '>= 99 %', 'umbral_aceptable' => '95-99 %', 'umbral_insuficiente' => '< 95 %', 'responsable' => 'Informática', 'plazo' => '2027-06-30', 'costo_estimado' => 1000000])->assertSessionHas('ok');
        }
        $this->assertSame(4, CalidadIniciativa::query()->count());
        $this->assertTrue(app(CumplimientoService::class)->subcaracteristicas()['cumple']);
        $ini = CalidadIniciativa::query()->first();
        $admin->post('/app/admin/cumplimiento/iniciativas/'.$ini->id, ['plataforma_id' => $sgd->id, 'descripcion' => 'Mejora ajustada', 'etapa' => 4, 'estado' => 'terminada', 'resultado' => 'Se alcanzó 99,5 %'])->assertSessionHas('ok');
        $this->assertSame([4, 'terminada'], [$ini->refresh()->etapa, $ini->estado]);
        $admin->post('/app/admin/cumplimiento/iniciativas', ['plataforma_id' => 99999, 'descripcion' => 'Algo', 'etapa' => 9, 'estado' => 'rara'])->assertSessionHasErrors(['plataforma_id', 'etapa', 'estado']);
        $admin->get('/app/admin/cumplimiento/informe')->assertOk()->assertSee('Informe del Plan de Mejora Continua')->assertSee('Mejora ajustada')->assertSee('Se alcanzó 99,5 %')->assertSee('cumple');
        $admin->get('/app/admin/cumplimiento/iniciativas/nueva')->assertOk()->assertSee('Subcaracterística');
        $admin->get('/app/admin/cumplimiento/plataformas/'.$sgd->id)->assertOk();
    }

    public function test_la_bitacora_muestra_y_exporta_la_hora_utc(): void
    {
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->get('/app/admin/cumplimiento')->assertOk();
        $this->comoFuncionario($admin)->post('/app/admin/cumplimiento/coordinador', ['titular' => 'A B']);
        $f = AuditLog::query()->latest('id')->first();
        $local = Carbon::parse(substr((string) $f->getRawOriginal('ts'), 0, 19), 'America/Santiago');
        $this->assertSame($local->copy()->utc()->format('Y-m-d H:i:s'), $f->tsUtc());
        $auditor = $this->comoFuncionario($this->usuario('auditor'));
        $auditor->get('/app/logs')->assertOk()->assertSee('UTC '.$f->tsUtc());
        $csv = $auditor->get('/app/logs/exportar')->streamedContent();
        $this->assertStringContainsString('fecha_utc', explode("\n", $csv)[0]);
    }

    public function test_los_metadatos_del_expediente_traen_utc_y_ubicacion_geografica(): void
    {
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $m = $e->refresh()->metadatos;
        $this->assertSame(['Santo Domingo', 'San Antonio', 'Valparaíso', 'Chile'], [$m['ubicacion']['comuna'], $m['ubicacion']['provincia'], $m['ubicacion']['region'], $m['ubicacion']['pais']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $m['temporalidad']['fecha_apertura_utc']);
        $this->assertSame(Carbon::parse($m['temporalidad']['fecha_apertura'])->utc()->format('Y-m-d\TH:i'), substr($m['temporalidad']['fecha_apertura_utc'], 0, 16));
    }

    public function test_el_comprobante_trae_los_datos_del_certificado_de_presentacion(): void
    {
        $s = $this->solicitudDOM();
        $html = view('pdf.comprobante', ['s' => $s->load('tramite'), 'anexos' => [], 'codigo' => 'ABC', 'qr' => '', 'url' => 'https://x', 'logoPdf' => null])->render();
        foreach (['Fecha y hora de presentación', 'UTC', 'Formulario web de solicitudes', 'Órgano ante el cual se tramita', 'PE-MUN-00243', 'Recepción realizada por', 'Plataforma SGD', 'Expediente vinculado', 'Se asigna cuando'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
    }

    public function test_el_formulario_informa_sobre_el_correo_como_domicilio_digital(): void
    {
        $this->comoCiudadano()->get('/ciudadano/nueva/2')->assertOk()->assertSee('correo no deseado')->assertSee('verifique que el remitente', false);
    }

    public function test_el_vecino_puede_cambiar_o_revocar_su_consentimiento(): void
    {
        $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => 2, 'correo' => 'a@b.cl', 'detalle' => 'Solicito certificado de número.', 'consentimiento' => 'si', 'canal_notificacion' => 'correo',
            'anexo_4' => $this->archivo('cedula.pdf', $this->pdfValido())])->assertSessionDoesntHaveErrors();
        $s = Solicitud::query()->firstOrFail();
        $this->assertTrue($s->consentimiento_datos);
        $c = $this->comoCiudadano();
        $c->get('/ciudadano/solicitudes/'.$s->folio)->assertOk()->assertSee('Dejar de autorizar el tratamiento');
        $c->post('/ciudadano/solicitudes/'.$s->folio.'/consentimiento', ['consentimiento' => 'si'])->assertSessionHas('error'); // ya es esa
        $c->post('/ciudadano/solicitudes/'.$s->folio.'/consentimiento', ['consentimiento' => 'no'])->assertSessionHas('ok');
        $this->assertFalse($s->refresh()->consentimiento_datos);
        $this->assertDatabaseHas('audit_log', ['accion' => 'solicitud.consentimiento_revocado']);
        $c->get('/ciudadano/solicitudes/'.$s->folio)->assertSee('Autorizar el tratamiento de mis datos')->assertSee('que no autoriza');
        $c->post('/ciudadano/solicitudes/'.$s->folio.'/consentimiento', ['consentimiento' => 'si'])->assertSessionHas('ok');
        $this->assertTrue($s->refresh()->consentimiento_datos);
        $this->assertDatabaseHas('audit_log', ['accion' => 'solicitud.consentimiento_otorgado']);
        $c->post('/ciudadano/solicitudes/'.$s->folio.'/consentimiento', ['consentimiento' => 'tal vez'])->assertSessionHasErrors('consentimiento');
        // otra persona no puede cambiar el de una solicitud ajena
        $this->withSession(['ciudadano' => ['run' => '20000009-9', 'nombres' => 'Otra', 'apellidos' => 'Vecina'], 'login_at' => time(), 'last_seen' => time()])->post('/ciudadano/solicitudes/'.$s->folio.'/consentimiento', ['consentimiento' => 'no'])->assertStatus(404);
        $this->assertTrue($s->refresh()->consentimiento_datos);
    }

    public function test_el_panel_dpo_lista_los_accesos_a_contenido_sensible(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $this->comoFuncionario($dom)->get('/app/expedientes/'.$e->id)->assertOk(); // registra expediente.ver sensible
        $r = $this->comoFuncionario($this->usuario('admin'))->get('/app/datos-personales')->assertOk();
        $r->assertSee('Accesos a contenido con datos sensibles')->assertSee('expediente.ver')->assertSee('expediente '.$e->id)->assertDontSee($dom->run);
        $this->comoFuncionario($this->usuario('admin'))->get('/app/datos-personales?acc_desde='.now()->addDay()->format('Y-m-d'))->assertSee('Sin accesos registrados con este criterio');
    }
}
