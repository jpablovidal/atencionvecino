<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\FirmaEstilo;
use App\Models\FirmaPosicion;
use App\Models\TipoDocumento;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\Firma\FirmaConfigService;
use App\Services\Firma\FirmaRenderer;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class FirmasConfigTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function admin()
    {
        return $this->comoFuncionario($this->usuario('admin'));
    }

    private function vigente(string $tipo = 'OFICIO', bool $clasico = false): Documento
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        if (! $clasico) {
            return $this->documentoVigente($e, $dom, $tipo);
        }
        $ds = app(DocumentoService::class);
        $d = $ds->crear($e, TipoDocumento::query()->where('codigo', $tipo)->first(), null, 'Doc 2 firmas', $dom);
        $alc = $this->usuario('alcalde');
        $sec = $this->usuario('secretario');
        $ds->definirFlujo($d, 'clasico', [], [$alc->id, $sec->id]);
        $ds->firmar($d->refresh(), $alc, '123456');

        return $ds->firmar($d->refresh(), $sec, '654321');
    }

    private function estilo(array $o = []): array
    {
        return array_merge(['nombre' => 'Mi estilo', 'texto_encabezado' => 'Firmado por', 'color_borde' => '#112233', 'color_fondo' => '#ffffee', 'color_texto' => '#000000', 'tamano_fuente' => 9, 'ancho_mm' => 80, 'alto_mm' => 30,
            'borde_estilo' => 'dashed', 'alineacion_texto' => 'center', 'mostrar_nombre' => 1, 'mostrar_cargo' => 1, 'mostrar_fecha' => 1, 'mostrar_codigo' => 1], $o);
    }

    public function test_el_sello_de_la_copia_publica_muestra_los_datos_del_firmante(): void
    {
        $d = $this->vigente();
        $html = app(PdfService::class)->htmlCopiaPublica($d, 'abc');
        foreach (['Firmado electrónicamente por', 'Sofía Secretaria Demo', 'Secretaria Municipal', 'Cód. validación: '.$d->codigo_validacion, 'hora de Chile'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->assertStringContainsString('FIRMA SIMULADA – sin validez legal', $html); // firma de prueba (mock)
        $this->assertStringNotContainsString('RUN ***', $html); // por defecto no se muestra el RUN
        $this->assertStringStartsWith('%PDF', app(PdfService::class)->copiaPublica($d, 'abc'));
    }

    public function test_estilo_propio_cambia_flags_y_colores_y_enmascara_el_run(): void
    {
        $this->admin()->post('/app/admin/firmas/estilos', $this->estilo(['mostrar_run' => 1, 'mostrar_cargo' => 0, 'mostrar_codigo' => 0, 'nombre' => 'Con RUN']))->assertSessionHas('ok');
        $e = FirmaEstilo::query()->where('nombre', 'Con RUN')->first();
        $this->admin()->post("/app/admin/firmas/estilos/{$e->id}/defecto")->assertSessionHas('ok');
        $d = $this->vigente();
        $html = app(PdfService::class)->htmlCopiaPublica($d, 'abc');
        $this->assertStringContainsString('#112233', $html);
        $this->assertStringContainsString('dashed', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->assertStringContainsString('RUN ***', $html);
        $sello = explode('Hoja de certificado', $html)[0];
        $this->assertStringNotContainsString('Secretaria Municipal', $sello); // cargo oculto en el sello
        $this->assertStringNotContainsString('Cód. validación:', $sello); // código oculto en el sello
        $this->assertStringNotContainsString('34567890', $html); // el RUN nunca completo
    }

    public function test_posicion_por_tipo_prevalece_sobre_la_general_y_primera_pagina_usa_coordenadas(): void
    {
        $tipo = TipoDocumento::query()->where('codigo', 'OFICIO')->first();
        $this->admin()->post('/app/admin/firmas/posiciones', ['nombre' => 'Oficio 1.ª arriba', 'tipo_documento_id' => $tipo->id, 'orden_firma' => 1, 'pagina' => 'primera', 'alineacion' => 'izquierda', 'margen_superior_mm' => 10, 'x_mm' => 20, 'y_mm' => 30, 'activo' => 1])->assertSessionHas('ok');
        $r = app(FirmaRenderer::class);
        $this->assertSame('primera', $r->resolver($tipo->id, 1)['pos']->pagina);
        $this->assertSame('ultima', $r->resolver(TipoDocumento::query()->where('codigo', 'DEC_ALC')->value('id'), 1)['pos']->pagina); // otro tipo: general
        $html = app(PdfService::class)->htmlCopiaPublica($this->vigente(), 'abc');
        $this->assertStringContainsString('position:absolute;left:20mm;top:30mm', $html);
        $this->assertStringStartsWith('%PDF', app(PdfService::class)->copiaPublica($this->vigente(), 'abc'));
    }

    public function test_dos_firmas_se_ubican_izquierda_y_derecha_segun_la_configuracion(): void
    {
        $d = $this->vigente('OFICIO', true);
        $html = app(PdfService::class)->htmlCopiaPublica($d, 'abc');
        $this->assertLessThan(strpos($html, 'Sofía'), strpos($html, 'Carlos')); // 1.ª firma (alcalde) antes que la 2.ª
        $this->assertStringContainsString('text-align:right', $html);
        $this->assertSame(2, substr_count(explode('Hoja de certificado', $html)[0], 'Firmado electrónicamente por'));
    }

    public function test_los_datos_del_sello_se_escapan(): void
    {
        $this->usuario('secretario')->forceFill(['nombres' => '<script>alert(1)</script>', 'cargo' => '<img src=x onerror=alert(1)>'])->save();
        $html = app(PdfService::class)->htmlCopiaPublica($this->vigente(), 'abc');
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_validaciones_de_estilo(): void
    {
        $svc = app(FirmaConfigService::class);
        $u = $this->usuario('admin');
        foreach ([
            ['color_texto' => '#aaaaaa', 'color_fondo' => '#ffffff'], // poco contraste
            ['color_borde' => 'rojo'], ['color_fondo' => '#fff'], ['color_texto' => 'javascript:alert(1)'], ['tamano_fuente' => 30], ['ancho_mm' => 10], ['alto_mm' => 200],
            ['borde_estilo' => 'groove'], ['texto_encabezado' => "linea1\nlinea2"], ['texto_encabezado' => ''], ['mostrar_nombre' => 0, 'mostrar_codigo' => 0], ['nombre' => ''],
        ] as $cambio) {
            try {
                $svc->guardarEstilo($this->estilo($cambio), null, $u);
                $this->fail('debió rechazar '.json_encode($cambio));
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
        $svc->guardarEstilo($this->estilo(['nombre' => 'Estilo válido']), null, $u);
        $this->addToAssertionCount(1);
    }

    public function test_nombre_de_estilo_unico_un_solo_defecto_y_no_se_desactiva_el_defecto(): void
    {
        $svc = app(FirmaConfigService::class);
        $u = $this->usuario('admin');
        $a = FirmaEstilo::query()->where('defecto', true)->first();
        try {
            $svc->guardarEstilo($this->estilo(['nombre' => $a->nombre]), null, $u);
            $this->fail('nombre duplicado');
        } catch (FlujoException) {
            $this->addToAssertionCount(1);
        }
        $b = $svc->guardarEstilo($this->estilo(['nombre' => 'Otro']), null, $u);
        $svc->definirEstiloDefecto($b);
        $this->assertSame(1, FirmaEstilo::query()->where('defecto', true)->count());
        $this->assertTrue($b->refresh()->defecto);
        $this->expectException(FlujoException::class);
        $svc->alternarEstilo($b); // el defecto no se desactiva
    }

    public function test_un_estilo_inactivo_no_se_usa_y_cae_al_defecto(): void
    {
        $svc = app(FirmaConfigService::class);
        $e = $svc->guardarEstilo($this->estilo(['nombre' => 'Temporal']), null, $this->usuario('admin'));
        $p = FirmaPosicion::query()->where('orden_firma', 1)->first();
        $p->forceFill(['estilo_id' => $e->id])->save();
        $this->assertSame('Temporal', app(FirmaRenderer::class)->resolver(null, 1)['estilo']->nombre);
        $svc->alternarEstilo($e);
        $this->assertSame('Estándar', (new FirmaRenderer)->resolver(null, 1)['estilo']->nombre);
        FirmaEstilo::query()->update(['activo' => false, 'defecto' => false]); // sin estilos: estilo integrado
        $this->assertSame('Integrado', (new FirmaRenderer)->resolver(null, 1)['estilo']->nombre);
    }

    public function test_validaciones_de_posicion(): void
    {
        $svc = app(FirmaConfigService::class);
        $u = $this->usuario('admin');
        $base = ['nombre' => 'P', 'tipo_documento_id' => null, 'orden_firma' => 3, 'pagina' => 'ultima', 'alineacion' => 'centro', 'margen_superior_mm' => 10, 'activo' => true];
        foreach ([
            ['pagina' => 'primera'], // sin coordenadas
            ['pagina' => 'primera', 'x_mm' => 200, 'y_mm' => 10], // se sale por la derecha (70 mm de ancho)
            ['pagina' => 'primera', 'x_mm' => 10, 'y_mm' => 270], // se sale por abajo
            ['pagina' => 'primera', 'x_mm' => -5, 'y_mm' => 10], ['orden_firma' => 9], ['pagina' => 'centro'], ['alineacion' => 'arriba'], ['margen_superior_mm' => 99], ['estilo_id' => 9999],
            ['orden_firma' => 1], // ya existe una activa (general, 1.ª)
        ] as $c) {
            try {
                $svc->guardarPosicion(array_merge($base, $c), null, $u);
                $this->fail('debió rechazar '.json_encode($c));
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
        $svc->guardarPosicion(array_merge($base, ['pagina' => 'primera', 'x_mm' => 100, 'y_mm' => 200]), null, $u);
        $this->addToAssertionCount(1);
    }

    public function test_activar_posicion_duplicada_se_rechaza(): void
    {
        $svc = app(FirmaConfigService::class);
        $u = $this->usuario('admin');
        $p = FirmaPosicion::query()->where('orden_firma', 1)->first();
        $svc->alternarPosicion($p); // inactiva
        $n = $svc->guardarPosicion(['nombre' => 'Nueva 1.ª', 'tipo_documento_id' => null, 'orden_firma' => 1, 'pagina' => 'ultima', 'alineacion' => 'centro', 'margen_superior_mm' => 8, 'activo' => true], null, $u);
        $this->assertTrue($n->activo);
        $this->expectException(FlujoException::class);
        $svc->alternarPosicion($p); // reactivar duplicaría
    }

    public function test_pagina_de_administracion_prueba_pdf_y_permisos(): void
    {
        foreach (['/app/admin/firmas', '/app/admin/firmas/prueba'] as $u) {
            $this->comoFuncionario($this->usuario('funcionario'))->get($u)->assertForbidden();
            $this->comoFuncionario($this->usuario('auditor'))->get($u)->assertForbidden();
        }
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/firmas/estilos', $this->estilo())->assertForbidden();
        $this->admin()->get('/app/admin/firmas')->assertOk()->assertSee('Vista previa')->assertSee('Firmado electrónicamente por')->assertSee('hoja-previa', false);
        $pdf = $this->admin()->get('/app/admin/firmas/prueba')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $tipo = TipoDocumento::query()->first()->id;
        $this->admin()->get("/app/admin/firmas?tipo=$tipo&editar_estilo=1")->assertOk()->assertSee('Editar estilo');
        $this->admin()->post('/app/admin/firmas/estilos', $this->estilo(['color_texto' => '#bbbbbb']))->assertSessionHas('error');
        $this->admin()->post('/app/admin/firmas/estilos', $this->estilo(['nombre' => 'Guardado por HTTP']))->assertSessionHas('ok');
        $this->assertDatabaseHas('audit_log', ['accion' => 'firmas.estilo_guardar']);
    }
}
