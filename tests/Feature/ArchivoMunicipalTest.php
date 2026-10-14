<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Microforma;
use App\Models\MicroformaEvento;
use App\Models\Usuario;
use App\Services\ArchivoMunicipalService;
use App\Services\FileVault;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class ArchivoMunicipalTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function archivista(): Usuario
    {
        return Usuario::query()->where('rol', 'arch_gestion')->firstOrFail();
    }

    private function ministro(): Usuario
    {
        return Usuario::query()->where('rol', 'arch_cert')->firstOrFail();
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['titulo' => 'Decretos alcaldicios 2019-2020', 'descripcion' => 'Microfilm de decretos.', 'tipo_soporte' => 'microfilm_16mm', 'unidad_id' => 1, 'serie' => 'Decretos', 'ubicacion' => 'Bodega 2, estante 4',
            'fecha_desde' => '2019-01-01', 'fecha_hasta' => '2020-12-31', 'n_documentos' => 120, 'n_imagenes' => 0];
    }

    /** Microforma en borrador con 2 archivos. */
    private function conArchivos(): Microforma
    {
        $m = app(ArchivoMunicipalService::class)->crear($this->archivista(), $this->datos());
        app(ArchivoMunicipalService::class)->subirArchivos($m, $this->archivista(), [$this->archivo('rollo1.pdf', $this->pdfValido('uno')), $this->archivo('rollo2.pdf', $this->pdfValido('dos'))]);

        return $m->refresh();
    }

    private function certificada(): Microforma
    {
        $m = $this->conArchivos();
        $s = app(ArchivoMunicipalService::class);
        $s->enviarARevision($m, $this->archivista());
        $s->certificar($m->refresh(), $this->ministro(), 'ACTA-2026/15', $this->archivo('acta.pdf', $this->pdfValido('acta')));

        return $m->refresh();
    }

    public function test_matriz_de_permisos(): void
    {
        foreach (['admin', 'alcalde', 'secretario', 'funcionario'] as $rol) {
            $this->assertFalse($this->usuario($rol)->tienePermiso('archivo.ver'), "$rol no ve el archivo por defecto");
        }
        $this->assertTrue($this->usuario('auditor')->tienePermiso('archivo.ver')); // solo lectura
        $this->assertFalse($this->usuario('auditor')->tienePermiso('archivo.gestionar'));
        $this->assertTrue($this->archivista()->tienePermiso('archivo.gestionar'));
        $this->assertFalse($this->archivista()->tienePermiso('archivo.certificar'));
        $this->assertTrue($this->ministro()->tienePermiso('archivo.certificar'));
        $this->assertTrue($this->ministro()->tienePermiso('archivo.disposicion'));
        $this->assertFalse($this->ministro()->tienePermiso('archivo.gestionar'));
        // el administrador no puede recibir permisos de contenido del archivo (techo del rol base)
        foreach (['archivo.ver', 'archivo.gestionar', 'archivo.certificar', 'archivo.disposicion'] as $p) {
            $this->assertNotContains($p, Permisos::techo('admin'), "admin no admite $p");
        }
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/archivo')->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/archivo')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/archivo')->assertOk();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/archivo/nueva')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->post('/app/archivo', $this->datos())->assertForbidden();
    }

    public function test_ciclo_completo_registro_revision_certificacion(): void
    {
        $arch = $this->archivista();
        $this->comoFuncionario($arch)->post('/app/archivo', $this->datos())->assertSessionHas('ok');
        $m = Microforma::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^MF-\d{4}-\d{6}$/', $m->codigo);
        $this->assertSame('borrador', $m->estado);
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/subir", ['archivos' => [$this->archivo('p1.pdf', $this->pdfValido('a'))]])->assertSessionHas('ok');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/indice", ['referencia' => 'Decreto N° 15/2019', 'descripcion' => 'Nombramiento', 'fojas' => '1-3', 'posicion' => 'F-001'])->assertSessionHas('ok');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/enviar")->assertSessionHas('ok');
        $m->refresh();
        $this->assertSame('en_revision', $m->estado);
        $this->assertNotNull($m->sha256_conjunto);
        $this->assertSame(1, $m->n_imagenes); // por defecto, la cantidad de archivos
        $this->comoFuncionario($this->ministro())->post("/app/archivo/{$m->id}/certificar", ['acta_numero' => 'ACTA-001', 'acta' => $this->archivo('acta.pdf', $this->pdfValido('acta'))])->assertSessionHas('ok');
        $m->refresh();
        $this->assertSame('certificada', $m->estado);
        $this->assertSame('ACTA-001', $m->acta_numero);
        $this->assertNotNull($m->acta_archivo_id);
        $this->comoFuncionario($arch)->get("/app/archivo/{$m->id}")->assertOk()->assertSee('ACTA-001')->assertSee($m->sha256_conjunto);
        $this->assertSame(['registro', 'carga_archivos', 'indice', 'envio_revision', 'certificacion'], MicroformaEvento::query()->where('microforma_id', $m->id)->orderBy('id')->pluck('tipo')->all());
        foreach (['archivo.crear', 'archivo.cargar', 'archivo.revision', 'archivo.certificar'] as $a) {
            $this->assertDatabaseHas('audit_log', ['accion' => $a, 'entidad_id' => (string) $m->id]);
        }
    }

    public function test_separacion_de_funciones_el_creador_no_certifica(): void
    {
        $m = $this->conArchivos();
        $s = app(ArchivoMunicipalService::class);
        $s->enviarARevision($m, $this->archivista());
        // un usuario con ambos permisos, que además registró la microforma, no puede certificarla
        $doble = Usuario::query()->where('rol', 'arch_cert')->firstOrFail();
        $m->forceFill(['creado_por' => $doble->id])->save();
        $this->expectException(FlujoException::class);
        $this->expectExceptionMessage('Separación de funciones');
        $s->certificar($m->refresh(), $doble, 'ACTA-X', $this->archivo('acta.pdf', $this->pdfValido('acta')));
    }

    public function test_certificada_es_inmutable(): void
    {
        $m = $this->certificada();
        $arch = $this->archivista();
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}", $this->datos(['titulo' => 'Título cambiado']))->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/subir", ['archivos' => [$this->archivo('x.pdf')]])->assertSessionHas('error');
        $a = $m->archivos()->first();
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/archivo/{$a->id}/quitar")->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/indice", ['referencia' => 'Nuevo'])->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/anular", ['motivo' => 'Intento de anulación no permitido'])->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/enviar")->assertSessionHas('error');
        $this->assertSame('Decretos alcaldicios 2019-2020', $m->refresh()->titulo);
        $this->assertSame('certificada', $m->estado);
        $this->assertSame(2, $m->archivos()->count());
    }

    public function test_certificar_exige_acta_pdf_numero_y_estado(): void
    {
        $m = $this->conArchivos();
        $s = app(ArchivoMunicipalService::class);
        try {
            $s->certificar($m, $this->ministro(), 'A-1', $this->archivo('acta.pdf'));
            $this->fail('no se puede certificar un borrador');
        } catch (FlujoException) {
            $this->assertSame('borrador', $m->refresh()->estado);
        }
        $s->enviarARevision($m, $this->archivista());
        $png = "\x89PNG\r\n\x1A\n".str_repeat('x', 40);
        foreach ([['', $this->archivo('a.pdf')], ['A<script>', $this->archivo('a.pdf')], ['A-1', null], ['A-1', $this->archivo('a.png', $png)], ['A-1', $this->archivo('mal.pdf', $this->pdfValido('/JavaScript (x)'))]] as [$num, $f]) {
            try {
                $s->certificar($m->refresh(), $this->ministro(), $num, $f);
                $this->fail("se aceptó: $num");
            } catch (FlujoException) {
                $this->assertSame('en_revision', $m->refresh()->estado);
            }
        }
        // quien solo gestiona no certifica
        $this->expectException(FlujoException::class);
        $s->certificar($m->refresh(), $this->archivista(), 'A-2', $this->archivo('acta.pdf'));
    }

    public function test_devolver_a_borrador_y_anular(): void
    {
        $m = $this->conArchivos();
        $s = app(ArchivoMunicipalService::class);
        $s->enviarARevision($m, $this->archivista());
        $this->comoFuncionario($this->ministro())->post("/app/archivo/{$m->id}/devolver", ['motivo' => 'corto'])->assertSessionHas('error');
        $this->comoFuncionario($this->ministro())->post("/app/archivo/{$m->id}/devolver", ['motivo' => 'Falta legibilidad en el rollo 2.'])->assertSessionHas('ok');
        $this->assertSame('borrador', $m->refresh()->estado);
        $this->assertNull($m->sha256_conjunto);
        $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m->id}/anular", ['motivo' => 'Registro duplicado del rollo.'])->assertSessionHas('ok');
        $this->assertSame('anulada', $m->refresh()->estado);
        $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m->id}/enviar")->assertSessionHas('error');
    }

    public function test_enviar_a_revision_exige_archivos(): void
    {
        $m = app(ArchivoMunicipalService::class)->crear($this->archivista(), $this->datos());
        $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m->id}/enviar")->assertSessionHas('error');
        $this->assertSame('borrador', $m->refresh()->estado);
    }

    public function test_subida_valida_contenido_y_no_deja_huerfanos(): void
    {
        $m = app(ArchivoMunicipalService::class)->crear($this->archivista(), $this->datos());
        $antes = Archivo::query()->count();
        $exe = $this->archivo('virus.pdf', "MZ\x90\x00 programa ejecutable");
        $activo = $this->archivo('activo.pdf', $this->pdfValido('/Launch /Action'));
        foreach ([$exe, $activo] as $malo) {
            $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m->id}/subir", ['archivos' => [$this->archivo('ok.pdf'), $malo]])->assertSessionHas('error');
        }
        $this->assertSame($antes, Archivo::query()->count(), 'ni el archivo bueno queda si otro falla');
        $this->assertSame(0, $m->archivos()->count());
    }

    public function test_archivos_cifrados_en_reposo(): void
    {
        $m = $this->conArchivos();
        $a = $m->archivos()->first();
        $bruto = app(FileVault::class)->blob($a->ruta);
        $this->assertStringNotContainsString('%PDF-1.4', $bruto);
        $this->assertSame(hash('sha256', $this->pdfValido('uno')), $a->sha256);
    }

    public function test_verificacion_de_integridad_detecta_alteracion(): void
    {
        $m = $this->certificada();
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/archivo/{$m->id}/verificar")->assertSessionHas('ok');
        $a = $m->archivos()->first();
        // alteración directa del archivo almacenado
        $ruta = config('sgd.storage_path').'/'.ltrim((string) preg_replace('#^[a-z]+:#', '', $a->ruta), '/');
        $archivos = glob(config('sgd.storage_path').'/*/*') ?: [];
        $this->assertNotEmpty($archivos);
        foreach ($archivos as $f) {
            if (is_file($f) && str_contains($f, substr(basename($a->ruta), 0, 8))) {
                file_put_contents($f, 'ALTERADO'.file_get_contents($f));
                $ruta = $f;
            }
        }
        $this->assertFileExists($ruta);
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/archivo/{$m->id}/verificar")->assertSessionHas('error');
        $this->assertDatabaseHas('archivo_eventos', ['microforma_id' => $m->id, 'tipo' => 'verificacion']);
        $this->comoFuncionario($this->usuario('auditor'))->get("/app/archivo/{$m->id}/archivo/{$a->id}")->assertNotFound(); // no entrega un archivo alterado
        $this->assertDatabaseHas('archivo_eventos', ['microforma_id' => $m->id, 'tipo' => 'consulta_fallida']);
    }

    public function test_descarga_solo_de_archivos_de_la_microforma_anti_idor(): void
    {
        $m1 = $this->conArchivos();
        $m2 = app(ArchivoMunicipalService::class)->crear($this->archivista(), $this->datos(['titulo' => 'Otra microforma distinta']));
        app(ArchivoMunicipalService::class)->subirArchivos($m2, $this->archivista(), [$this->archivo('otro.pdf', $this->pdfValido('otro'))]);
        $ajeno = $m2->archivos()->first();
        $propio = $m1->archivos()->first();
        $aud = $this->usuario('auditor');
        $this->comoFuncionario($aud)->get("/app/archivo/{$m1->id}/archivo/{$ajeno->id}")->assertNotFound();
        $r = $this->comoFuncionario($aud)->get("/app/archivo/{$m1->id}/archivo/{$propio->id}")->assertOk();
        $this->assertSame($this->pdfValido('uno'), $r->getContent());
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('archivo_eventos', ['microforma_id' => $m1->id, 'tipo' => 'consulta']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'archivo.descarga']);
        // un archivo de solicitud ciudadana no es accesible por esta vía
        $s = $this->solicitudDOM();
        $anexo = Archivo::query()->where('solicitud_id', $s->id)->first();
        $this->comoFuncionario($aud)->get("/app/archivo/{$m1->id}/archivo/{$anexo->id}")->assertNotFound();
        $this->comoFuncionario($this->usuario('funcionario'))->get("/app/archivo/{$m1->id}/archivo/{$propio->id}")->assertForbidden();
        // el índice solo se puede tocar dentro de su propia microforma
        $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m2->id}/indice", ['referencia' => 'Ref 1'])->assertSessionHas('ok');
        $item = $m2->items()->first();
        $this->comoFuncionario($this->archivista())->post("/app/archivo/{$m1->id}/indice/{$item->id}/quitar")->assertSessionHas('error');
        $this->assertSame(1, $m2->items()->count());
    }

    public function test_textos_planos_y_validaciones(): void
    {
        $arch = $this->archivista();
        $this->comoFuncionario($arch)->post('/app/archivo', $this->datos(['titulo' => 'Título <script>alert(1)</script> con ‮invisibles​', 'descripcion' => '<img src=x onerror=alert(1)> desc']))->assertSessionHas('ok');
        $m = Microforma::query()->firstOrFail();
        foreach ([$m->titulo, $m->descripcion] as $c) {
            $this->assertStringNotContainsString('<', $c);
            $this->assertStringNotContainsString("\u{202E}", $c);
            $this->assertStringNotContainsString("\u{200B}", $c);
        }
        $html = $this->comoFuncionario($arch)->get("/app/archivo/{$m->id}")->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        foreach ([['titulo' => 'abc'], ['tipo_soporte' => 'cinta'], ['fecha_desde' => '2021-01-01', 'fecha_hasta' => '2020-01-01'], ['unidad_id' => 999], ['n_documentos' => -1]] as $mal) {
            $n = Microforma::query()->count();
            $this->comoFuncionario($arch)->post('/app/archivo', $this->datos($mal));
            $this->assertSame($n, Microforma::query()->count(), json_encode($mal));
        }
        $this->comoFuncionario($arch)->get('/app/archivo?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
        $this->comoFuncionario($arch)->get('/app/archivo?estado=hack')->assertSessionHasErrors('estado');
        $this->comoFuncionario($arch)->get('/app/archivo?q=%25')->assertOk()->assertDontSee($m->codigo); // % literal
    }

    public function test_disposicion_de_originales_con_doble_control(): void
    {
        $m = $this->certificada();
        $arch = $this->archivista();
        $min = $this->ministro();
        // antes de certificar no aplica
        $otra = $this->conArchivos();
        $this->comoFuncionario($arch)->post("/app/archivo/{$otra->id}/disposicion", ['tipo' => 'eliminar', 'motivo' => str_repeat('Fundamento. ', 4)])->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion", ['tipo' => 'eliminar', 'motivo' => 'corto'])->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion", ['tipo' => 'quemar', 'motivo' => str_repeat('Fundamento. ', 4)])->assertSessionHasErrors('tipo');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion", ['tipo' => 'eliminar', 'motivo' => 'Plazo de conservación cumplido según la tabla de retención.'])->assertSessionHas('ok');
        $m->refresh();
        $this->assertSame('pendiente', $m->disposicion_estado);
        // el solicitante no puede autorizar, aunque tuviera el permiso; y sin permiso tampoco
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion/resolver", ['decision' => 'autorizar', 'acta' => 'DA-1'])->assertForbidden();
        $m->forceFill(['disposicion_solicitada_por' => $min->id])->save();
        $this->comoFuncionario($min)->post("/app/archivo/{$m->id}/disposicion/resolver", ['decision' => 'autorizar', 'acta' => 'DA-1'])->assertSessionHas('error');
        $m->forceFill(['disposicion_solicitada_por' => $arch->id])->save();
        $this->comoFuncionario($min)->post("/app/archivo/{$m->id}/disposicion/resolver", ['decision' => 'autorizar', 'acta' => ''])->assertSessionHas('error');
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion/ejecutar")->assertSessionHas('error'); // aún no autorizada
        $this->comoFuncionario($min)->post("/app/archivo/{$m->id}/disposicion/resolver", ['decision' => 'autorizar', 'acta' => 'DA-1/2026'])->assertSessionHas('ok');
        $this->assertSame('autorizada', $m->refresh()->disposicion_estado);
        $this->comoFuncionario($arch)->post("/app/archivo/{$m->id}/disposicion/ejecutar")->assertSessionHas('ok');
        $this->assertSame('ejecutada', $m->refresh()->disposicion_estado);
        $this->assertSame('DA-1/2026', $m->disposicion_acta);
        foreach (['disposicion_solicitada', 'disposicion_autorizada', 'disposicion_ejecutada'] as $t) {
            $this->assertDatabaseHas('archivo_eventos', ['microforma_id' => $m->id, 'tipo' => $t]);
        }
        // rechazo: vuelve a «sin solicitud»
        $m2 = $this->certificada();
        $this->comoFuncionario($arch)->post("/app/archivo/{$m2->id}/disposicion", ['tipo' => 'transferir', 'motivo' => 'Transferencia al archivo regional por convenio vigente.'])->assertSessionHas('ok');
        $this->comoFuncionario($min)->post("/app/archivo/{$m2->id}/disposicion/resolver", ['decision' => 'rechazar', 'motivo' => 'x'])->assertSessionHas('error');
        $this->comoFuncionario($min)->post("/app/archivo/{$m2->id}/disposicion/resolver", ['decision' => 'rechazar', 'motivo' => 'Aún dentro del plazo de conservación.'])->assertSessionHas('ok');
        $this->assertSame('no_aplica', $m2->refresh()->disposicion_estado);
    }

    public function test_cadena_de_custodia_es_de_solo_escritura(): void
    {
        $m = $this->conArchivos();
        $e = MicroformaEvento::query()->where('microforma_id', $m->id)->firstOrFail();
        try {
            $e->update(['tipo' => 'x']);
            $this->fail('inmutable');
        } catch (\LogicException) {
            $this->assertSame('registro', $e->refresh()->tipo);
        }
        $this->expectException(\LogicException::class);
        $e->delete();
    }

    public function test_constancia_pdf_y_aviso_legal(): void
    {
        $m = $this->certificada();
        $r = $this->comoFuncionario($this->usuario('auditor'))->get("/app/archivo/{$m->id}/constancia")->assertOk();
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $html = view('pdf.microforma', ['m' => $m->load('unidad', 'creador', 'certificador', 'items', 'eventos.usuario'), 'archivos' => $m->archivos()->get(), 'acta' => Archivo::query()->find($m->acta_archivo_id)])->render();
        $this->assertStringContainsString('No reemplaza el certificado del ministro de fe', $html);
        $this->assertStringContainsString($m->sha256_conjunto, $html);
        $this->comoFuncionario($this->usuario('funcionario'))->get("/app/archivo/{$m->id}/constancia")->assertForbidden();
    }

    public function test_listado_con_filtros_y_menu(): void
    {
        $m = $this->certificada();
        $arch = $this->archivista();
        $this->comoFuncionario($arch)->get('/app/archivo')->assertOk()->assertSee($m->codigo);
        $this->comoFuncionario($arch)->get('/app/archivo?estado=borrador')->assertDontSee($m->codigo);
        $this->comoFuncionario($arch)->get('/app/archivo?estado=certificada&soporte=microfilm_16mm&unidad=1')->assertSee($m->codigo);
        $this->comoFuncionario($arch)->get('/app/archivo?q=bodega')->assertSee($m->codigo);
        $this->comoFuncionario($arch)->get('/app')->assertSee('Archivo municipal');
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertDontSee('/app/archivo');
    }

    public function test_microforma_inexistente_404_y_csrf_en_formularios(): void
    {
        $this->comoFuncionario($this->archivista())->get('/app/archivo/9999')->assertNotFound();
        $m = $this->conArchivos();
        $html = $this->comoFuncionario($this->archivista())->get("/app/archivo/{$m->id}")->getContent();
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'name="_token"'));
    }

    public function test_hash_del_conjunto_cambia_si_cambia_el_orden_o_contenido(): void
    {
        $m = $this->conArchivos();
        $s = app(ArchivoMunicipalService::class);
        $h1 = $s->hashConjunto($m);
        $a = $m->archivos()->first();
        $s->quitarArchivo($m, $this->archivista(), $a->id);
        $this->assertNotSame($h1, $s->hashConjunto($m->refresh()));
    }
}
