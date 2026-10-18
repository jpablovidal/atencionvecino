<?php

namespace Tests\Feature;

use App\Models\Anuncio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\Escenario;
use Tests\TestCase;

class AnunciosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function datos(array $x = []): array
    {
        return $x + ['titulo' => 'Cierre por feriado', 'descripcion' => "Línea 1\nLínea 2", 'audiencia' => 'vecinos', 'activo' => 1, 'orden' => 1];
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($im);
        $b = (string) ob_get_clean();
        $p = tempnam(sys_get_temp_dir(), 'an');
        file_put_contents($p, $b);

        return new UploadedFile($p, 'x.png', 'image/png', null, true);
    }

    private function mp4(): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'an');
        file_put_contents($p, "\0\0\0\x18ftypmp42".str_repeat("\0", 400));

        return new UploadedFile($p, 'v.mp4', 'video/mp4', null, true);
    }

    public function test_solo_quien_tiene_el_permiso_administra_anuncios(): void
    {
        foreach (['admin', 'oirs'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->get('/app/anuncios')->assertOk()->assertSee('Anuncios del sitio');
        }
        foreach (['funcionario', 'auditor', 'autoridad'] as $rol) {
            auth()->forgetGuards();
            $t = $this->comoFuncionario($this->usuario($rol));
            $t->get('/app/anuncios')->assertForbidden();
            $t->post('/app/anuncios', $this->datos())->assertForbidden();
        }
        $this->assertSame(0, Anuncio::query()->count());
    }

    public function test_crea_con_imagen_y_el_modal_aparece_en_el_portal(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        $adm->post('/app/anuncios', $this->datos(['medio' => $this->png(), 'medio_alt' => 'Afiche del cierre']))->assertRedirect('/app/anuncios');
        $a = Anuncio::query()->firstOrFail();
        $this->assertSame('imagen', $a->medio_tipo);
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/')->assertOk()->assertSee('sgd-anuncio-modal', false)->assertSee('Cierre por feriado')->assertSee('Afiche del cierre');
        $r = $this->get("/anuncios/{$a->id}/medio")->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
    }

    public function test_video_con_rangos_y_firma_validada(): void
    {
        $this->comoFuncionario($this->usuario('admin'))->post('/app/anuncios', $this->datos(['medio' => $this->mp4(), 'medio_alt' => 'Saludo del alcalde']))->assertRedirect('/app/anuncios');
        $a = Anuncio::query()->firstOrFail();
        $this->assertSame('video', $a->medio_tipo);
        auth()->forgetGuards();
        $this->withHeaders(['Range' => 'bytes=0-9'])->get("/anuncios/{$a->id}/medio")->assertStatus(206)->assertHeader('Content-Range');
        $mal = tempnam(sys_get_temp_dir(), 'an');
        file_put_contents($mal, '<?php echo 1; ?>'.str_repeat('a', 100));
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->post('/app/anuncios', $this->datos(['titulo' => 'Otro anuncio', 'medio' => new UploadedFile($mal, 'v.mp4', 'video/mp4', null, true), 'medio_alt' => 'x algo']))->assertSessionHas('error');
        $this->assertSame(1, Anuncio::query()->count());
    }

    public function test_el_medio_exige_texto_alternativo_y_el_texto_no_admite_html(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        $adm->post('/app/anuncios', $this->datos(['medio' => $this->png()]))->assertSessionHas('error');
        $adm->post('/app/anuncios', $this->datos(['titulo' => '<script>alert(1)</script>Aviso', 'descripcion' => '<b onmouseover=x>hola</b>']))->assertRedirect('/app/anuncios');
        $a = Anuncio::query()->firstOrFail();
        $this->assertStringNotContainsString('<', $a->titulo.$a->descripcion);
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/')->assertDontSee('<script>alert(1)', false);
    }

    public function test_enlace_solo_https_o_ruta_interna(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        foreach (['javascript:alert(1)', '//evil.example', 'http://inseguro.cl', 'data:text/html,x'] as $url) {
            $adm->post('/app/anuncios', $this->datos(['enlace_url' => $url]))->assertSessionHas('error');
        }
        $adm->post('/app/anuncios', $this->datos(['enlace_url' => '/tramites']))->assertRedirect('/app/anuncios');
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Con https', 'enlace_url' => 'https://www.santodomingo.cl/x']))->assertRedirect('/app/anuncios');
        $this->assertSame(2, Anuncio::query()->count());
    }

    public function test_vigencia_audiencia_y_activacion(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Futuro', 'inicia_en' => now()->addDays(3)->format('Y-m-d')]));
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Solo personal', 'audiencia' => 'funcionarios']));
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Para todos', 'audiencia' => 'todos']));
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Vencido', 'inicia_en' => now()->subDays(5)->format('Y-m-d'), 'termina_en' => now()->subDays(2)->format('Y-m-d')]));
        $adm->post('/app/anuncios', $this->datos(['titulo' => 'Termina antes', 'inicia_en' => '2026-05-10', 'termina_en' => '2026-05-01']))->assertSessionHas('error');
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/')->assertSee('Para todos')->assertDontSee('Solo personal')->assertDontSee('Futuro')->assertDontSee('Vencido');
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertSee('Solo personal')->assertSee('Para todos');
        $todos = Anuncio::query()->where('titulo', 'Para todos')->firstOrFail();
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->post("/app/anuncios/{$todos->id}/alternar")->assertSessionHas('ok');
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/')->assertDontSee('Para todos');
        $this->get("/anuncios/{$todos->id}/medio")->assertNotFound();
    }

    public function test_el_medio_de_un_anuncio_vencido_no_es_publico_pero_si_para_vista_previa(): void
    {
        $this->comoFuncionario($this->usuario('admin'))->post('/app/anuncios', $this->datos(['medio' => $this->png(), 'medio_alt' => 'Afiche viejo', 'termina_en' => now()->subDay()->format('Y-m-d'), 'inicia_en' => now()->subDays(4)->format('Y-m-d')]));
        $a = Anuncio::query()->firstOrFail();
        auth()->forgetGuards();
        $this->flushSession();
        $this->get("/anuncios/{$a->id}/medio")->assertNotFound();
        $this->comoFuncionario($this->usuario('admin'))->get("/anuncios/{$a->id}/medio")->assertOk();
    }

    public function test_editar_reemplazar_y_eliminar_borra_el_medio(): void
    {
        $adm = $this->comoFuncionario($this->usuario('admin'));
        $adm->post('/app/anuncios', $this->datos(['medio' => $this->png(), 'medio_alt' => 'Primera imagen']));
        $a = Anuncio::query()->firstOrFail();
        $ruta = $a->medio_ruta;
        $adm->post("/app/anuncios/{$a->id}", $this->datos(['titulo' => 'Título nuevo', 'quitar_medio' => 1]))->assertRedirect('/app/anuncios');
        $a->refresh();
        $this->assertSame('Título nuevo', $a->titulo);
        $this->assertNull($a->medio_tipo);
        $this->assertNotSame('', $ruta);
        $adm->post("/app/anuncios/{$a->id}/eliminar")->assertRedirect('/app/anuncios');
        $this->assertSame(0, Anuncio::query()->count());
    }
}
