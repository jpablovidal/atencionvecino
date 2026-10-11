<?php

namespace Tests\Feature;

use App\Models\Archivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class SubidasHttpTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_solicitud_rechaza_ejecutable_disfrazado_y_no_deja_basura(): void
    {
        $this->sembrar();
        $r = $this->comoCiudadano()->post('/ciudadano/solicitudes', [
            'tramite_id' => 2, 'correo' => 'a@b.cl', 'detalle' => 'Solicito certificado de número.', 'canal_notificacion' => 'correo',
            'anexo_4' => $this->archivo('cedula.pdf', "MZ\x90\x00\x03 ejecutable"),
        ]);
        $r->assertSessionHas('error');
        $this->assertDatabaseCount('solicitudes', 0);
        $this->assertDatabaseCount('archivos', 0);
    }

    public function test_anexo_obligatorio_faltante_y_pdf_con_js(): void
    {
        $this->sembrar();
        $base = ['tramite_id' => 2, 'correo' => 'a@b.cl', 'detalle' => 'Solicito certificado de número.', 'canal_notificacion' => 'correo'];
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $base)->assertSessionHas('error');
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $base + ['anexo_4' => $this->archivo('c.pdf', "%PDF-1.4\n<</JS(x)>>\n%%EOF")])->assertSessionHas('error');
        $this->assertDatabaseCount('solicitudes', 0);
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $base + ['anexo_4' => $this->archivo()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('solicitudes', 1);
        $this->assertDatabaseCount('archivos', 2); // anexo + comprobante
    }

    public function test_limite_de_cantidad_de_archivos(): void
    {
        $this->sembrar();
        $extras = array_map(fn () => $this->archivo(), range(1, 9));
        $this->comoCiudadano()->post('/ciudadano/solicitudes', [
            'tramite_id' => 2, 'correo' => 'a@b.cl', 'detalle' => 'Solicito certificado de número.', 'canal_notificacion' => 'correo', 'anexo_4' => $this->archivo(), 'extras' => $extras,
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('solicitudes', 0);
    }

    public function test_archivos_almacenados_cifrados_fuera_del_docroot(): void
    {
        $this->sembrar();
        $this->solicitudDOM();
        $a = Archivo::query()->first();
        $ruta = config('sgd.storage_path').'/'.$a->ruta;
        $this->assertFileExists($ruta);
        $this->assertStringNotContainsString('%PDF', file_get_contents($ruta));
        $this->assertStringStartsNotWith(public_path(), $ruta);
    }
}
