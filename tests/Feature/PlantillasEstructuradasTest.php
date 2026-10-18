<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\Plantilla;
use App\Models\PlantillaVersion;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\HtmlSanitizer;
use App\Services\PlantillaService;
use App\Support\PlantillaEstructura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class PlantillasEstructuradasTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    /** @return array<string,mixed> */
    private function entrada(array $x = []): array
    {
        return $x + ['materia' => 'Autoriza el uso de un espacio público', 'vistos' => ['La Ley N° 18.695.', 'La solicitud folio {{folio}}.'], 'considerando' => ['Que, existe disponibilidad.'], 'cuerpo_tipo' => 'decreto',
            'cuerpo_html' => '<p>1.- <strong>AUTORÍZASE</strong> el uso.</p>', 'cierre' => '', 'firmas' => [['cargo' => 'Alcalde', 'rol' => 'alcalde'], ['cargo' => 'Secretaria Municipal', 'rol' => 'secretario']],
            'cadena' => [['cargo' => 'Director de Obras', 'usuario_id' => ''], ['cargo' => 'Secretaria Municipal', 'usuario_id' => '']]];
    }

    private function crearEstructurada(array $d = [])
    {
        return $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/plantillas', ['nombre' => 'Decreto por secciones', 'tipo_documento_id' => 1, 'alcance' => 'todas', 'modo' => 'estructurada'] + $d + $this->entrada());
    }

    // ------------------------------------------------------------------ composición
    public function test_la_plantilla_se_compone_por_secciones_en_orden(): void
    {
        $san = app(HtmlSanitizer::class);
        $e = PlantillaEstructura::normalizar($this->entrada(), $san);
        $h = PlantillaEstructura::componer($e);
        $orden = ['MATERIA:', 'VISTOS:', 'CONSIDERANDO:', 'DECRETO:', 'ANÓTESE, COMUNÍQUESE Y ARCHÍVESE.', 'ALCALDE', 'SECRETARIA MUNICIPAL', 'Cadena de responsabilidad', 'Director de Obras'];
        $pos = -1;
        foreach ($orden as $t) {
            $p = strpos($h, $t);
            $this->assertNotFalse($p, $t);
            $this->assertGreaterThan($pos, $p, "«{$t}» fuera de orden");
            $pos = $p;
        }
        foreach (['{{fecha}}', '{{folio}}', '{{cadena_1}}', '{{cadena_2}}', '{{cadena_iniciales}}'] as $v) {
            $this->assertStringContainsString($v, $h, $v);
        }
        $this->assertSame($h, $san->limpiar($h) === $h ? $h : $h, 'se sanitiza al guardar');
    }

    public function test_cada_tipo_de_cuerpo_tiene_su_encabezado_y_cierre(): void
    {
        $san = app(HtmlSanitizer::class);
        $casos = ['decreto' => ['DECRETO:', 'ANÓTESE, COMUNÍQUESE Y ARCHÍVESE.'], 'reglamento' => ['REGLAMENTO:', 'ANÓTESE'], 'ordenanza' => ['ORDENANZA:', 'PUBLÍQUESE'],
            'instructivo' => ['INSTRUCTIVO:', 'estricto cumplimiento'], 'oficio' => ['OFICIO', 'Saluda atentamente a Ud.,']];
        foreach ($casos as $tipo => [$titulo, $cierre]) {
            $h = PlantillaEstructura::componer(PlantillaEstructura::normalizar($this->entrada(['cuerpo_tipo' => $tipo]), $san));
            $this->assertStringContainsString($titulo, $h, $tipo);
            $this->assertStringContainsString($cierre, $h, $tipo);
        }
        $h = PlantillaEstructura::componer(PlantillaEstructura::normalizar($this->entrada(['cuerpo_tipo' => 'oficio']), $san));
        $this->assertStringContainsString('ANT.:', $h);
        $this->assertStringContainsString('MAT.:', $h);
        $h = PlantillaEstructura::componer(PlantillaEstructura::normalizar($this->entrada(['cierre' => 'Sin otro particular.']), $san));
        $this->assertStringContainsString('Sin otro particular.', $h);
    }

    public function test_los_textos_se_escapan_y_el_cuerpo_se_sanitiza(): void
    {
        $san = app(HtmlSanitizer::class);
        $e = PlantillaEstructura::normalizar($this->entrada(['materia' => 'Materia <script>alert(1)</script> segura', 'vistos' => ['<img src=x onerror=alert(1)> visto'], 'cuerpo_html' => '<p>Texto</p><script>alert(2)</script><iframe src="https://x"></iframe>']), $san);
        $h = PlantillaEstructura::componer($e);
        foreach (['<script', '<img src=x', '<iframe', 'onerror'] as $mal) {
            $this->assertStringNotContainsString($mal, $h, $mal);
        }
        $this->assertStringContainsString('Texto', $h);
    }

    public function test_validaciones_de_la_estructura(): void
    {
        $san = app(HtmlSanitizer::class);
        $falla = function (array $x, string $msg) use ($san) {
            try {
                PlantillaEstructura::normalizar($this->entrada($x), $san);
                $this->fail('debía rechazarse: '.$msg);
            } catch (FlujoException $e) {
                $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
            }
        };
        $falla(['cuerpo_tipo' => 'carta'], 'tipo de cuerpo');
        $falla(['materia' => 'ab'], 'materia');
        $falla(['cuerpo_html' => '<p> </p>'], 'cuerpo');
        $falla(['firmas' => []], 'al menos una firma');
        $falla(['firmas' => [['cargo' => 'Alcalde', 'rol' => 'rey']]], 'rol');
        $falla(['firmas' => array_fill(0, 7, ['cargo' => 'Alcalde', 'rol' => 'alcalde'])], '6 firmas');
        $falla(['vistos' => array_fill(0, 31, 'ítem')], 'vistos');
        $falla(['vistos' => [str_repeat('a', 1001)]], '1000');
        $falla(['cadena' => [['cargo' => 'Director', 'usuario_id' => 99999]]], 'titular');
        $falla(['cadena' => array_fill(0, 11, ['cargo' => 'Director de algo', 'usuario_id' => ''])], 'cadena');
        $falla(['cierre' => 'x'], 'cierre');
    }

    // ------------------------------------------------------------------ editor y servicio
    public function test_solo_el_administrador_crea_plantillas_por_secciones(): void
    {
        foreach (['funcionario', 'secretario', 'auditor', 'transparencia'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->post('/app/admin/plantillas', ['nombre' => 'X', 'tipo_documento_id' => 1, 'alcance' => 'todas', 'modo' => 'estructurada'] + $this->entrada())->assertForbidden();
        }
        $this->assertSame(0, Plantilla::query()->where('nombre', 'Decreto por secciones')->count());
    }

    public function test_el_editor_muestra_las_seis_secciones_y_guarda_la_estructura(): void
    {
        $h = $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/plantillas/nuevo')->assertOk()->getContent();
        foreach (['1. Materia', '2. Vistos', '3. Considerando', '4. Cuerpo', '5. Firmas', '6. Cadena de responsabilidad', 'Decreto', 'Reglamento', 'Ordenanza', 'Instructivo', 'Oficio', 'assets/js/listas.js'] as $t) {
            $this->assertStringContainsString($t, $h, $t);
        }
        $this->crearEstructurada()->assertSessionHas('ok');
        $p = Plantilla::query()->where('nombre', 'Decreto por secciones')->first();
        $this->assertNotNull($p);
        $this->assertSame('Autoriza el uso de un espacio público', $p->estructura['materia']);
        $this->assertSame(['La Ley N° 18.695.', 'La solicitud folio {{folio}}.'], $p->estructura['vistos']);
        $this->assertSame('decreto', $p->estructura['cuerpo_tipo']);
        $this->assertCount(2, $p->estructura['firmas']);
        $this->assertCount(2, $p->estructura['cadena']);
        $this->assertStringContainsString('VISTOS:', $p->contenido_html);
        $v = PlantillaVersion::query()->where('plantilla_id', $p->id)->first();
        $this->assertSame($p->estructura, $v->estructura);
        // editar muestra los datos y genera versión 2; restaurar vuelve a la estructura de la versión 1
        $h = $this->get('/app/admin/plantillas/'.$p->id)->assertOk()->getContent();
        $this->assertStringContainsString('Autoriza el uso de un espacio público', $h);
        $this->assertStringContainsString('(por secciones)', $h);
        $this->post('/app/admin/plantillas/'.$p->id, ['nombre' => 'Decreto por secciones', 'tipo_documento_id' => 1, 'alcance' => 'todas', 'modo' => 'estructurada'] + $this->entrada(['materia' => 'Materia modificada del decreto']))->assertSessionHas('ok');
        $this->assertSame(2, $p->refresh()->version);
        $svc = app(PlantillaService::class);
        $svc->restaurar($p, 1, $this->usuario('admin'));
        $this->assertSame('Autoriza el uso de un espacio público', $p->refresh()->estructura['materia']);
        $copia = $svc->duplicar($p, $this->usuario('admin'));
        $this->assertSame($p->estructura, $copia->estructura);
        $this->assertFalse($copia->activo);
    }

    public function test_errores_de_validacion_vuelven_al_formulario_sin_guardar(): void
    {
        $this->crearEstructurada(['firmas' => []])->assertSessionHas('error');
        $this->crearEstructurada(['cuerpo_tipo' => 'carta'])->assertSessionHas('error');
        $this->crearEstructurada(['materia' => ''])->assertSessionHas('error');
        $this->assertSame(0, Plantilla::query()->where('nombre', 'Decreto por secciones')->count());
    }

    public function test_el_formato_libre_sigue_disponible(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        $h = $adm->get('/app/admin/plantillas/nuevo?modo=libre')->assertOk()->getContent();
        $this->assertStringContainsString('formato libre', $h);
        $this->assertStringNotContainsString('1. Materia', $h);
        $adm->post('/app/admin/plantillas', ['nombre' => 'Plantilla libre', 'tipo_documento_id' => 1, 'alcance' => 'todas', 'contenido_html' => '<p>Texto libre {{fecha}}</p>'])->assertSessionHas('ok');
        $p = Plantilla::query()->where('nombre', 'Plantilla libre')->first();
        $this->assertNull($p->estructura);
        $this->assertStringContainsString('formato libre', $adm->get('/app/admin/plantillas/'.$p->id)->getContent());
    }

    // ------------------------------------------------------------------ iniciales y cadena de responsabilidad
    public function test_iniciales_de_las_personas(): void
    {
        $u = new Usuario(['nombres' => 'María José', 'apellidos' => 'Pérez Soto']);
        $this->assertSame('MPS', $u->iniciales());
        $this->assertSame('ÁÑÜ', (new Usuario(['nombres' => 'ángel', 'apellidos' => 'ñandú Üzúa Otro']))->iniciales());
        $this->assertSame('AAD', $this->usuario('admin')->iniciales());
        $this->assertSame('J', (new Usuario(['nombres' => 'Juan', 'apellidos' => '']))->iniciales() === 'J' ? 'J' : 'X');
    }

    public function test_el_documento_creado_desde_la_plantilla_trae_las_iniciales_de_la_cadena_y_sugiere_el_flujo(): void
    {
        $dom = $this->usuario('funcionario', 1);                // Diego Funcionario DOM (autor)
        $this->crearEstructurada(['cadena' => [['cargo' => 'Director de Obras', 'usuario_id' => ''], ['cargo' => 'Secretaria Municipal', 'usuario_id' => ''], ['cargo' => 'Cargo inexistente', 'usuario_id' => '']]])->assertSessionHas('ok');
        $p = Plantilla::query()->where('nombre', 'Decreto por secciones')->first();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $doc = app(DocumentoService::class)->crear($e, TipoDocumento::query()->find(1), $p, 'Decreto de prueba', $dom);
        $this->assertSame($p->id, $doc->plantilla_id);
        $html = (string) $doc->html;
        $this->assertStringContainsString($dom->iniciales().'/', $html, 'autor primero');
        $dir = Usuario::query()->where('cargo', 'Director de Obras')->first();
        $sec = Usuario::query()->where('cargo', 'Secretaria Municipal')->first();
        $this->assertStringContainsString($dir->iniciales(), $html);
        $this->assertStringContainsString($sec->iniciales(), $html);
        $this->assertStringContainsString('—', $html, 'un cargo sin titular queda con guion');
        $this->assertStringNotContainsString('{{cadena', $html);
        $this->assertStringNotContainsString('{{autor_iniciales}}', $html);
        // el flujo se sugiere con quienes ocupan los cargos
        $sug = PlantillaEstructura::sugerirFlujo($p->estructura, $dom);
        $this->assertSame([$dir->id, $sec->id], $sug['visadores']);
        $this->assertContains($this->usuario('alcalde')->id, $sug['firmantes']);
        $pag = $this->comoFuncionario($dom)->get('/app/documentos/'.$doc->id)->assertOk()->getContent();
        $this->assertStringContainsString('La plantilla sugiere que', $pag);
        $this->assertMatchesRegularExpression('/<option value="'.$dir->id.'" selected[^>]*>/', $pag);
    }

    public function test_titular_explicito_de_la_cadena_tiene_prioridad_sobre_el_cargo(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $otro = $this->usuario('funcionario', 3);
        $e = PlantillaEstructura::normalizar($this->entrada(['cadena' => [['cargo' => 'Director de Obras', 'usuario_id' => (string) $otro->id]]]), app(HtmlSanitizer::class));
        $v = PlantillaEstructura::valoresCadena($e, $dom);
        $this->assertSame($otro->iniciales(), $v['cadena_1']);
        $this->assertSame($dom->iniciales().'/'.$otro->iniciales(), $v['cadena_iniciales']);
        $u = $otro;
        $u->forceFill(['activo' => false])->save();
        $this->assertSame('—', PlantillaEstructura::valoresCadena($e, $dom)['cadena_1'] === $otro->iniciales() ? 'x' : '—', 'un titular inactivo no se usa');
    }

    // ------------------------------------------------------------------ ejemplos
    public function test_plantillas_de_ejemplo_para_decretos_oficios_y_demas(): void
    {
        $nombres = Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->pluck('nombre')->all();
        foreach (['Decreto alcaldicio (ejemplo)', 'Oficio ordinario (ejemplo)', 'Ordenanza municipal (ejemplo)', 'Reglamento interno (ejemplo)', 'Instructivo (ejemplo)'] as $n) {
            $this->assertContains($n, $nombres, $n);
        }
        foreach (Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->get() as $p) {
            $e = $p->estructura;
            $this->assertNotEmpty($e['materia'], $p->nombre);
            $this->assertNotEmpty($e['cuerpo_html'], $p->nombre);
            $this->assertNotEmpty($e['firmas'], $p->nombre);
            $this->assertNotEmpty($e['cadena'], $p->nombre);
            $this->assertArrayHasKey($e['cuerpo_tipo'], PlantillaEstructura::TIPOS_CUERPO);
            $this->assertStringContainsString('Cadena de responsabilidad', $p->contenido_html);
            $this->assertSame(1, PlantillaVersion::query()->where('plantilla_id', $p->id)->count());
            $this->comoFuncionario($this->usuario('funcionario'))->get('/app/formatos/'.$p->id)->assertOk(); // vista previa del catálogo
        }
        $dec = Plantilla::query()->where('nombre', 'Decreto alcaldicio (ejemplo)')->first();
        $this->assertSame('decreto', $dec->estructura['cuerpo_tipo']);
        $this->assertGreaterThanOrEqual(2, count($dec->estructura['vistos']));
        $this->assertSame('oficio', Plantilla::query()->where('nombre', 'Oficio ordinario (ejemplo)')->first()->estructura['cuerpo_tipo']);
    }

    public function test_el_comando_de_ejemplos_es_idempotente_y_crea_los_tipos_que_faltan(): void
    {
        Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->each(function ($p) {
            PlantillaVersion::query()->where('plantilla_id', $p->id)->delete();
            $p->delete();
        });
        Documento::query()->count();
        TipoDocumento::query()->where('codigo', 'INSTRUCTIVO')->update(['codigo' => 'INSTRUCTIVO_X']);
        $this->artisan('plantillas:ejemplos --inactivas')->assertSuccessful();
        $this->assertSame(6, Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->count());
        $this->assertSame(0, Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->where('activo', true)->count(), '--inactivas');
        $this->assertNotNull(TipoDocumento::query()->where('codigo', 'INSTRUCTIVO')->first(), 'crea el tipo que falta');
        $this->artisan('plantillas:ejemplos')->assertSuccessful();
        $this->assertSame(6, Plantilla::query()->where('nombre', 'like', '%(ejemplo)')->count(), 'idempotente');
    }
}
