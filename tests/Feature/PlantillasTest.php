<?php

namespace Tests\Feature;

use App\Models\Plantilla;
use App\Models\PlantillaVersion;
use App\Models\RecursoImagen;
use App\Models\Unidad;
use App\Services\ExpedienteService;
use App\Services\HtmlSanitizer;
use App\Services\PlantillaService;
use App\Services\RecursoImagenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class PlantillasTest extends TestCase
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

    private function datos(array $c = []): array
    {
        return array_merge(['nombre' => 'Oficio de salud', 'tipo_documento_id' => 3, 'descripcion' => 'Para la dirección', 'alcance' => 'todas',
            'contenido_html' => '<p>Santo Domingo, {{fecha}}</p><p>Estimado/a {{solicitante_nombre}}</p>'], $c);
    }

    private function png(int $w = 120, int $h = 60, string $nombre = 'logo.png'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 100, 180));
        ob_start();
        imagepng($im);

        return $this->subida((string) ob_get_clean(), $nombre);
    }

    private function subida(string $bytes, string $nombre): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'pl');
        file_put_contents($p, $bytes);

        return new UploadedFile($p, $nombre, null, null, true);
    }

    // ------------------------------------------------------------------ sanitizador
    public function test_el_editor_completo_conserva_formato_tablas_e_imagenes_de_la_biblioteca(): void
    {
        $san = app(HtmlSanitizer::class);
        $html = '<h5>Título</h5><p style="color:#ff0000;background-color:#ffff00;font-size:14pt;font-family:Arial;line-height:1.5;text-align:justify;text-indent:20px"><sub>a</sub><sup>b</sup><del>c</del></p>'
            .'<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><caption>Cuadro</caption><thead><tr><th scope="col" style="width:30%">A</th></tr></thead>'
            .'<tbody><tr><td colspan="1" style="border:1px solid #000;vertical-align:top">1</td></tr></tbody></table>'
            .'<img src="/app/recursos/12" alt="Logo" width="100" height="50" style="float:left"><div style="page-break-after: always;">&nbsp;</div><pre>x</pre>';
        $o = $san->limpiar($html);
        foreach (['<h5>', 'color:#ff0000', 'background-color:#ffff00', 'font-size:14pt', 'text-indent:20px', '<sub>', '<caption>', 'scope="col"', 'cellpadding="4"', 'border-collapse:collapse', 'vertical-align:top', 'page-break-after:always', '<pre>'] as $esperado) {
            $this->assertStringContainsString($esperado, str_replace('; ', ';', $o), $esperado);
        }
        $this->assertMatchesRegularExpression('#<img src="/app/recursos/12" alt="Logo" width="100" height="50"#', $o);
    }

    public function test_el_sanitizador_solo_admite_imagenes_de_la_biblioteca_y_bloquea_vectores(): void
    {
        $san = app(HtmlSanitizer::class);
        $malos = ['<img src="https://evil.cl/x.png">', '<img src="//evil.cl/x.png">', '<img src="data:image/png;base64,AAAA">', '<img src="/storage/secreto.png">', '<img src="/app/recursos/1/../../x">',
            '<img src="/app/recursos/abc">', '<img src="javascript:alert(1)">', '<img src="/app/recursos/1" onerror="alert(1)">', '<iframe src="/app/recursos/1"></iframe>', '<svg><script>alert(1)</script></svg>',
            '<p style="background:url(https://evil.cl/x)">x</p>', '<p style="position:fixed;top:0">x</p>', '<p style="font-family:Impact">x</p>', '<a href="javascript:alert(1)">x</a>', '<form action="/x"><input></form>', '<style>*{display:none}</style>'];
        foreach ($malos as $m) {
            $o = $san->limpiar($m);
            $this->assertDoesNotMatchRegularExpression('#evil\.cl|data:image|/storage/|javascript:|onerror|<iframe|<svg|<script|<form|<input|<style|position:|url\(|Impact|recursos/abc|\.\./#i', $o, "pasó: $m → $o");
        }
        $this->assertStringContainsString('src="/app/recursos/1"', $san->limpiar('<img src="/app/recursos/1" onerror="alert(1)">'));
    }

    // ------------------------------------------------------------------ mantenedor y versiones
    public function test_crear_editar_y_versionar_una_plantilla(): void
    {
        $this->admin()->post('/app/admin/plantillas', $this->datos())->assertSessionHas('ok');
        $p = Plantilla::query()->where('nombre', 'Oficio de salud')->first();
        $this->assertSame(1, $p->version);
        $this->assertSame(1, PlantillaVersion::query()->where('plantilla_id', $p->id)->count());
        // sin cambios: no hay versión nueva
        $this->admin()->post("/app/admin/plantillas/{$p->id}", $this->datos(['descripcion' => 'Otra descripción']))->assertSessionHas('ok');
        $this->assertSame(1, $p->fresh()->version);
        // con cambios: versión 2
        $this->admin()->post("/app/admin/plantillas/{$p->id}", $this->datos(['contenido_html' => '<p>Nuevo texto</p>']))->assertSessionHas('ok');
        $this->assertSame(2, $p->fresh()->version);
        $this->assertSame(['2', '1'], PlantillaVersion::query()->where('plantilla_id', $p->id)->orderByDesc('version')->pluck('version')->map(fn ($v) => (string) $v)->all());
        $this->admin()->get("/app/admin/plantillas/{$p->id}/versiones")->assertOk()->assertSee('Restaurar');
        $this->admin()->get("/app/admin/plantillas/{$p->id}/versiones/1")->assertOk()->assertSee('Estimado/a');
        // restaurar v1 → v3 con el contenido de v1
        $this->admin()->post("/app/admin/plantillas/{$p->id}/versiones/1/restaurar")->assertSessionHas('ok');
        $this->assertSame(3, $p->fresh()->version);
        $this->assertStringContainsString('Estimado/a', $p->fresh()->contenido_html);
        $this->assertDatabaseHas('audit_log', ['accion' => 'plantilla.restaurar']);
        // las versiones son inmutables
        $this->expectException(\LogicException::class);
        PlantillaVersion::query()->where('plantilla_id', $p->id)->first()->update(['nombre' => 'x']);
    }

    public function test_validaciones_de_la_plantilla(): void
    {
        $this->admin()->post('/app/admin/plantillas', $this->datos(['alcance' => 'unidades']))->assertSessionHas('error'); // sin unidades
        $this->admin()->post('/app/admin/plantillas', $this->datos(['contenido_html' => '<p> </p><script>x</script>']))->assertSessionHas('error');
        $this->admin()->post('/app/admin/plantillas', $this->datos(['alcance' => 'otro']))->assertSessionHasErrors('alcance');
        $this->admin()->post('/app/admin/plantillas', $this->datos(['unidades' => [9999], 'alcance' => 'unidades']))->assertSessionHasErrors('unidades.0');
        $this->admin()->post('/app/admin/plantillas', $this->datos(['tipo_documento_id' => 999]))->assertSessionHasErrors('tipo_documento_id');
        $this->assertSame(0, Plantilla::query()->where('nombre', 'Oficio de salud')->count());
    }

    public function test_duplicar_crea_una_copia_inactiva_y_activar_desactivar_se_audita(): void
    {
        $orig = Plantilla::query()->first();
        $this->admin()->post("/app/admin/plantillas/{$orig->id}/duplicar")->assertSessionHas('ok');
        $c = Plantilla::query()->where('nombre', 'like', 'Copia de%')->first();
        $this->assertFalse($c->activo);
        $this->assertSame($orig->contenido_html, $c->contenido_html);
        $this->admin()->post("/app/admin/plantillas/{$c->id}/estado")->assertSessionHas('ok');
        $this->assertTrue($c->fresh()->activo);
        $this->assertDatabaseHas('audit_log', ['accion' => 'plantilla.duplicar']);
    }

    public function test_solo_administracion_gestiona_plantillas(): void
    {
        foreach (['funcionario', 'alcalde', 'secretario', 'auditor'] as $r) {
            $h = $this->comoFuncionario($this->usuario($r));
            $h->get('/app/admin/plantillas')->assertForbidden();
            $h->post('/app/admin/plantillas', $this->datos())->assertForbidden();
        }
        $this->admin()->get('/app/admin/plantillas')->assertOk();
        $this->admin()->get('/app/admin/plantillas/nuevo')->assertOk()->assertSee('data-upload-url', false)->assertSee('Toda la municipalidad');
    }

    // ------------------------------------------------------------------ visibilidad por unidad
    public function test_visibilidad_por_direccion_salud_educacion_u_otra(): void
    {
        $salud = Unidad::query()->create(['codigo' => 'SALUD', 'nombre' => 'Dirección de Salud', 'activo' => true]);
        $educ = Unidad::query()->create(['codigo' => 'EDUC', 'nombre' => 'Dirección de Educación', 'activo' => true]);
        $this->admin()->post('/app/admin/plantillas', $this->datos(['nombre' => 'Formato Salud', 'alcance' => 'unidades', 'unidades' => [$salud->id]]))->assertSessionHas('ok');
        $this->admin()->post('/app/admin/plantillas', $this->datos(['nombre' => 'Formato General', 'alcance' => 'todas']))->assertSessionHas('ok');
        $fs = Plantilla::query()->where('nombre', 'Formato Salud')->first();
        $this->assertSame([$salud->id], $fs->unidades()->pluck('unidades.id')->all());

        $dom = $this->usuario('funcionario', 1); // DOM
        $this->comoFuncionario($dom)->get('/app/formatos')->assertOk()->assertSee('Formato General')->assertDontSee('Formato Salud');
        $this->get("/app/formatos/{$fs->id}")->assertNotFound();
        $this->get("/app/formatos/{$fs->id}/pdf")->assertNotFound();

        $dom->update(['unidad_id' => $salud->id]); // pasa a Salud
        $this->comoFuncionario($dom->fresh())->get('/app/formatos')->assertSee('Formato Salud')->assertSee('Dirección de Salud');
        $this->get("/app/formatos/{$fs->id}")->assertOk()->assertSee('Estimado/a');

        $dom->update(['unidad_id' => $educ->id]);
        $this->comoFuncionario($dom->fresh())->get('/app/formatos')->assertDontSee('Formato Salud');
        // el filtro por dirección muestra los generales y los de esa dirección
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/formatos?unidad='.$salud->id)->assertSee('Formato Salud')->assertSee('Formato General');
        $this->get('/app/formatos?unidad='.$educ->id)->assertDontSee('Formato Salud')->assertSee('Formato General');
        // administrador y auditor ven todo
        $this->admin()->get('/app/formatos')->assertSee('Formato Salud');
        // inactiva: no aparece en el catálogo ni se abre (salvo administración)
        $this->admin()->post("/app/admin/plantillas/{$fs->id}/estado");
        $dom->update(['unidad_id' => $salud->id]);
        $this->comoFuncionario($dom->fresh())->get('/app/formatos')->assertDontSee('Formato Salud');
        $this->get("/app/formatos/{$fs->id}")->assertNotFound();
        $this->admin()->get("/app/formatos/{$fs->id}")->assertOk();
    }

    public function test_cambiar_a_toda_la_municipalidad_limpia_las_unidades(): void
    {
        $this->admin()->post('/app/admin/plantillas', $this->datos(['alcance' => 'unidades', 'unidades' => [3, 4]]));
        $p = Plantilla::query()->where('nombre', 'Oficio de salud')->first();
        $this->assertCount(2, $p->unidades);
        $this->admin()->post("/app/admin/plantillas/{$p->id}", $this->datos(['alcance' => 'todas', 'unidades' => [3, 4]]));
        $this->assertCount(0, $p->fresh()->unidades);
    }

    public function test_el_documento_solo_se_crea_con_plantillas_visibles_y_del_tipo(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $this->admin()->post('/app/admin/plantillas', $this->datos(['nombre' => 'Solo DIDECO', 'alcance' => 'unidades', 'unidades' => [4]]));
        $this->admin()->post('/app/admin/plantillas', $this->datos(['nombre' => 'General propia', 'contenido_html' => '<p>Texto propio ABC {{expediente}}</p>']));
        $ded = Plantilla::query()->where('nombre', 'Solo DIDECO')->first();
        $gen = Plantilla::query()->where('nombre', 'General propia')->first();
        $h = $this->comoFuncionario($dom);
        $h->get("/app/expedientes/{$e->id}")->assertSee('General propia')->assertDontSee('Solo DIDECO');
        // plantilla no visible para su unidad
        $h->post("/app/expedientes/{$e->id}/documentos", ['tipo_documento_id' => 3, 'plantilla_id' => $ded->id, 'titulo' => 'X'])->assertSessionHas('error');
        // plantilla de otro tipo
        $h->post("/app/expedientes/{$e->id}/documentos", ['tipo_documento_id' => 1, 'plantilla_id' => $gen->id, 'titulo' => 'X'])->assertSessionHas('error');
        // la elegida es la que se usa (antes se tomaba "la primera del tipo")
        $h->post("/app/expedientes/{$e->id}/documentos", ['tipo_documento_id' => 3, 'plantilla_id' => $gen->id, 'titulo' => 'Oficio con formato'])->assertSessionHas('ok');
        $doc = $e->documentos()->where('titulo', 'Oficio con formato')->first();
        $this->assertStringContainsString('Texto propio ABC', $doc->html);
        $this->assertStringContainsString($e->codigo, $doc->html);
    }

    public function test_catalogo_para_todo_el_personal_con_el_permiso(): void
    {
        foreach (['funcionario', 'alcalde', 'secretario', 'auditor', 'admin'] as $r) {
            $this->comoFuncionario($this->usuario($r))->get('/app/formatos')->assertOk();
        }
        $this->get('/app/formatos')->assertSee('Formatos y plantillas');
        $this->flushSession();
        auth()->forgetGuards();
        $this->get('/app/formatos')->assertRedirect();
        // rol personalizado sin plantillas.ver
        DB::table('roles')->insert(['clave' => 'sin_cat', 'nombre' => 'Sin catálogo', 'rol_base' => 'funcionario', 'sistema' => 0, 'activo' => 1]);
        $u = $this->usuario('funcionario');
        $u->update(['rol' => 'sin_cat']);
        $this->comoFuncionario($u->fresh())->get('/app/formatos')->assertForbidden();
    }

    public function test_vista_previa_con_datos_de_ejemplo_y_pdf(): void
    {
        $this->admin()->post('/app/admin/plantillas', $this->datos());
        $p = Plantilla::query()->where('nombre', 'Oficio de salud')->first();
        $this->comoFuncionario($this->usuario('funcionario'))->get("/app/formatos/{$p->id}")->assertOk()->assertSee('María Pérez González')->assertDontSee('{{solicitante_nombre}}', false);
        $r = $this->get("/app/formatos/{$p->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertArrayHasKey('fecha', PlantillaService::VARIABLES);
    }

    // ------------------------------------------------------------------ imágenes
    public function test_subir_imagen_la_guarda_cifrada_y_la_sirve_solo_a_personal(): void
    {
        $r = $this->admin()->postJson('/app/recursos', ['file' => $this->png(2400, 800)]);
        $r->assertOk();
        $loc = $r->json('location');
        $this->assertMatchesRegularExpression('#^/app/recursos/\d+$#', $loc);
        $this->assertSame(1600, $r->json('ancho')); // reescalada
        $rec = RecursoImagen::query()->first();
        $this->assertSame(hash('sha256', app(RecursoImagenService::class)->contenido($rec)['bytes']), $rec->sha256);
        // en disco NO está en claro
        $crudo = (string) file_get_contents(config('sgd.storage_path').'/'.(str_starts_with($rec->ruta, 'gd:') ? '' : $rec->ruta));
        $this->assertStringNotContainsString("\x89PNG", $crudo);
        $img = $this->get($loc)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('image/png', $img->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $img->getContent());
        $this->get($loc, ['If-None-Match' => $img->headers->get('ETag')])->assertStatus(304);
        // el ciudadano y el anónimo no acceden
        $this->flushSession();
        auth()->forgetGuards();
        $this->get($loc)->assertRedirect();
        $this->comoCiudadano()->get($loc)->assertRedirect();
        // misma imagen = mismo recurso (deduplicación)
        $this->admin()->postJson('/app/recursos', ['file' => $this->png(2400, 800)])->assertOk();
        $this->assertSame(1, RecursoImagen::query()->count());
    }

    public function test_subida_rechaza_formatos_peligrosos_y_exige_permiso(): void
    {
        $malos = ['svg' => $this->subida('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg'), 'php' => $this->subida('<?php system($_GET[1]); ?>', 'a.png'),
            'gif' => $this->subida(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'a.gif'), 'corrupto' => $this->subida("\x89PNG\r\n\x1a\n".random_bytes(40), 'a.png'),
            'diminuto' => $this->png(4, 4), 'pesado' => $this->subida("\x89PNG\r\n\x1a\n".str_repeat('A', 2200000), 'a.png')];
        foreach ($malos as $n => $f) {
            $this->admin()->postJson('/app/recursos', ['file' => $f])->assertStatus(422);
        }
        $this->assertSame(0, RecursoImagen::query()->count(), 'se aceptó una imagen inválida');
        $this->admin()->postJson('/app/recursos', [])->assertStatus(422);
        // el auditor y el ciudadano no suben; un funcionario (documentos.crear) sí
        $this->comoFuncionario($this->usuario('auditor'))->postJson('/app/recursos', ['file' => $this->png()])->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->postJson('/app/recursos', ['file' => $this->png()])->assertOk();
    }

    public function test_el_re_codificado_elimina_metadatos_y_carga_incrustada(): void
    {
        $im = imagecreatetruecolor(100, 40);
        ob_start();
        imagejpeg($im, null, 90);
        $jpg = substr((string) ob_get_clean(), 0, 2)."\xFF\xFE\x00\x0ASECRETO!".'<?php echo 1; ?>';
        ob_start();
        imagejpeg($im, null, 90);
        $jpg = substr($jpg, 0, 2).substr((string) ob_get_clean(), 2).'<?php echo 1; ?>';
        $r = $this->admin()->postJson('/app/recursos', ['file' => $this->subida($jpg, 'foto.jpg')])->assertOk();
        $b = app(RecursoImagenService::class)->contenido(RecursoImagen::query()->find((int) basename($r->json('location'))))['bytes'];
        $this->assertStringNotContainsString('SECRETO!', $b);
        $this->assertStringNotContainsString('<?php', $b);
    }

    public function test_las_imagenes_se_incrustan_en_el_pdf_y_son_inmutables(): void
    {
        $r = $this->admin()->postJson('/app/recursos', ['file' => $this->png()])->assertOk();
        $loc = $r->json('location');
        $svc = app(RecursoImagenService::class);
        $out = $svc->incrustar('<img src="'.$loc.'" alt=""> <img src="/app/recursos/9999" alt="">');
        $this->assertStringContainsString('src="data:image/png;base64,', $out);
        $this->assertStringContainsString('src=""', $out); // inexistente: se omite, no se filtra la ruta
        $this->assertStringNotContainsString($loc, $out);
        $this->admin()->post('/app/admin/plantillas', $this->datos(['contenido_html' => '<p>Con logo</p><img src="'.$loc.'" alt="Logo" width="120" height="60">']))->assertSessionHas('ok');
        $p = Plantilla::query()->where('nombre', 'Oficio de salud')->first();
        $this->assertStringContainsString($loc, $p->contenido_html);
        $pdf = $this->get("/app/formatos/{$p->id}/pdf")->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1500, strlen($pdf));
        $this->expectException(\LogicException::class);
        RecursoImagen::query()->first()->delete();
    }

    public function test_el_documento_con_imagen_pasa_por_el_mismo_sanitizador(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/documentos", ['tipo_documento_id' => 3, 'titulo' => 'Doc'])->assertSessionHas('ok');
        $doc = $e->documentos()->first();
        $this->comoFuncionario($dom)->get("/app/documentos/{$doc->id}")->assertOk()->assertSee('data-upload-url', false);
    }
}
