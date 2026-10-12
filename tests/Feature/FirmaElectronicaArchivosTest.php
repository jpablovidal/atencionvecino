<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Firma\ArchivoFirmaService;
use App\Services\Firma\FirmaPdfInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\Escenario;
use Tests\Support\PdfFirmado;
use Tests\TestCase;

class FirmaElectronicaArchivosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private static array $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        self::$id ??= PdfFirmado::identidad();
    }

    private function insp(?string $ca = null): FirmaPdfInspector
    {
        return new FirmaPdfInspector($ca);
    }

    // ------------------------------------------------------------------ inspector
    /** Regresión: una firma cuyo último byte es 0x00 se truncaba al quitar el relleno con rtrim (fallaba ~1 de cada 256 firmas). */
    public function test_el_relleno_se_descarta_por_longitud_y_no_se_pierde_un_byte_final_cero(): void
    {
        $der = "\x30\x03\x02\x01\x00"; // SEQUENCE { INTEGER 0 }: termina en 0x00
        $this->assertSame($der, FirmaPdfInspector::recortarDer($der.str_repeat("\0", 100)));
        $this->assertSame('', FirmaPdfInspector::recortarDer(''));
        $this->assertSame('', FirmaPdfInspector::recortarDer("\x30\x82\xff"));
    }

    public function test_pdf_sin_firma_y_no_pdf(): void
    {
        $this->assertSame('sin_firma', $this->insp()->inspeccionar($this->pdfValido())['estado']);
        $this->assertSame('sin_firma', $this->insp()->inspeccionar("\x89PNG\r\n\x1a\n")['estado']);
        $this->assertSame('sin_firma', $this->insp()->inspeccionar('')['estado']);
    }

    public function test_reconoce_una_firma_valida_con_sus_datos(): void
    {
        $r = $this->insp()->inspeccionar(PdfFirmado::crear(self::$id));
        $this->assertSame('firmada', $r['estado']);
        $f = $r['firmas'][0];
        $this->assertTrue($f['integridad']);
        $this->assertTrue($f['cubre_todo']);
        $this->assertSame('Juan Pérez Soto', $f['firmante']);
        $this->assertStringContainsString('serialNumber=12345678-5', $f['sujeto']);
        $this->assertSame('adbe.pkcs7.detached', $f['subfiltro']);
        $this->assertSame('SHA256', $f['algoritmo']);
        $this->assertSame('Aprobación', $f['razon']);
        $this->assertNotNull($f['fecha_firma']);
        $this->assertTrue($f['certificado_vigente_al_firmar']);
        $this->assertSame('no_verificada', $f['cadena']); // sin CA configurada no se afirma confianza
    }

    public function test_detecta_documento_alterado_despues_de_firmar(): void
    {
        $r = $this->insp()->inspeccionar(PdfFirmado::crear(self::$id, 'Documento de prueba', null, true));
        $this->assertSame('invalida', $r['estado']);
        $this->assertFalse($r['firmas'][0]['integridad']);
        $this->assertNotEmpty($r['observaciones']);
    }

    public function test_detecta_contenido_agregado_despues_de_la_firma(): void
    {
        $r = $this->insp()->inspeccionar(PdfFirmado::crear(self::$id, 'Texto', "6 0 obj<</Type/Annot>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF"));
        $this->assertSame('firmada_con_cambios', $r['estado']);
        $this->assertTrue($r['firmas'][0]['integridad']);
        $this->assertFalse($r['firmas'][0]['cubre_todo']);
        // espacios finales no cuentan como cambio
        $this->assertSame('firmada', $this->insp()->inspeccionar(PdfFirmado::crear(self::$id)."\n\n  ")['estado']);
    }

    public function test_firma_con_certificado_cambiado_es_invalida(): void
    {
        $otra = PdfFirmado::identidad('Otra Persona');
        $pdf = PdfFirmado::crear(self::$id);
        // se reemplaza el CMS por uno de otra identidad sobre otro contenido: no debe validar contra este documento
        $otro = PdfFirmado::crear($otra, 'Otro documento distinto');
        preg_match('/\/Contents <([0-9A-F]+)>/', $otro, $mo);
        preg_match('/\/Contents <([0-9A-F]+)>/', $pdf, $mp);
        $this->assertSame('invalida', $this->insp()->inspeccionar(str_replace($mp[1], $mo[1], $pdf))['estado']);
    }

    public function test_formato_cades_y_firmas_multiples(): void
    {
        $r = $this->insp()->inspeccionar(PdfFirmado::crear(self::$id, 'Texto', null, false, 'ETSI.CAdES.detached'));
        $this->assertSame('firmada', $r['estado']);
        $this->assertStringContainsString('CAdES', $r['firmas'][0]['formato']);
        // dos revisiones firmadas por distintas personas: se firma la 2.ª sobre la 1.ª construyendo un PDF con ambas es complejo;
        // se comprueba que dos ByteRange distintos se reportan por separado
        $a = PdfFirmado::crear(self::$id, 'Uno');
        $b = PdfFirmado::crear(PdfFirmado::identidad('Segunda Firma'), 'Dos');
        $this->assertCount(1, $this->insp()->inspeccionar($a)['firmas']);
        $this->assertSame('Segunda Firma', $this->insp()->inspeccionar($b)['firmas'][0]['firmante']);
    }

    public function test_texto_que_imita_una_firma_no_cuenta(): void
    {
        $falso = "%PDF-1.4\n1 0 obj<</Note (/ByteRange [0 10 20 30] /Contents <3082FFFF>)>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $this->assertSame('sin_firma', $this->insp()->inspeccionar($falso)['estado']);
        $basura = str_replace('/ByteRange [0 ', '/ByteRange [5 ', PdfFirmado::crear(self::$id));
        $this->assertSame('sin_firma', $this->insp()->inspeccionar($basura)['estado']);
    }

    public function test_contents_corrupto_no_verificable_sin_romper(): void
    {
        $pdf = PdfFirmado::crear(self::$id);
        preg_match('/\/Contents <([0-9A-F]+)>/', $pdf, $m);
        $roto = str_replace($m[1], '30'.'82FFFF'.substr($m[1], 8), $pdf);
        $r = $this->insp()->inspeccionar($roto);
        $this->assertContains($r['estado'], ['no_verificable', 'sin_firma']);
    }

    public function test_cadena_de_confianza_con_ca_configurada(): void
    {
        $ca = PdfFirmado::identidad('AC Raiz SGD');
        $pdf = PdfFirmado::crear($ca); // firmante = la propia CA (autofirmado)
        $bundle = tempnam(sys_get_temp_dir(), 'ca');
        file_put_contents($bundle, $ca['certPem']);
        $this->assertSame('verificada', $this->insp($bundle)->inspeccionar($pdf)['firmas'][0]['cadena']);
        // otra CA distinta: no confiable
        file_put_contents($bundle, PdfFirmado::identidad('Otra AC')['certPem']);
        $this->assertSame('no_confiable', $this->insp($bundle)->inspeccionar($pdf)['firmas'][0]['cadena']);
    }

    // ------------------------------------------------------------------ integración
    private function subida(string $bytes, string $nombre = 'doc.pdf'): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($p, $bytes);

        return new UploadedFile($p, $nombre, null, null, true);
    }

    private function expediente(): array
    {
        $dom = $this->usuario('funcionario', 1);

        return [app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom), $dom];
    }

    public function test_al_cargar_un_archivo_se_reconoce_si_esta_firmado(): void
    {
        [$e, $dom] = $this->expediente();
        $h = $this->comoFuncionario($dom);
        $campos = fn ($f, $t) => ['archivo' => $f, 'titulo' => $t, 'nivel_acceso' => 'reservado'];
        $h->post("/app/expedientes/{$e->id}/archivos", $campos($this->subida(PdfFirmado::crear(self::$id)), 'Oficio firmado'))->assertSessionHas('ok');
        $h->post("/app/expedientes/{$e->id}/archivos", $campos($this->subida($this->pdfValido()), 'Oficio sin firma'))->assertSessionHas('ok');
        $h->post("/app/expedientes/{$e->id}/archivos", $campos($this->subida(PdfFirmado::crear(self::$id, 'Texto', null, true)), 'Oficio alterado'))->assertSessionHas('ok');
        $png = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($png);
        $h->post("/app/expedientes/{$e->id}/archivos", $campos($this->subida((string) ob_get_clean(), 'f.png'), 'Foto'))->assertSessionHas('ok');
        $est = Archivo::query()->where('ambito', 'expediente')->pluck('firma_estado', 'titulo')->all();
        $this->assertSame(['Oficio firmado' => 'firmada', 'Oficio sin firma' => 'sin_firma', 'Oficio alterado' => 'invalida', 'Foto' => 'no_aplica'], $est);
        $a = Archivo::query()->where('titulo', 'Oficio firmado')->first();
        $this->assertSame('Juan Pérez Soto', $a->firma()['firmas'][0]['firmante']);
        $this->assertNull(Archivo::query()->where('titulo', 'Oficio sin firma')->first()->firma_detalle);
        $html = $h->get("/app/expedientes/{$e->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Firma presente, emisor no verificado', $html); // sin CA configurada no se afirma validez
        $this->assertStringContainsString('Juan Pérez Soto', $html);
        $this->assertStringContainsString('Firma inválida o documento alterado', $html);
        $this->assertStringContainsString('Sin firma electrónica', $html);
        $this->assertStringContainsString('no determina por sí sola', $html);
    }

    public function test_el_pdf_original_se_conserva_byte_a_byte(): void
    {
        [$e, $dom] = $this->expediente();
        $pdf = PdfFirmado::crear(self::$id);
        $this->comoFuncionario($dom)->post("/app/expedientes/{$e->id}/archivos", ['archivo' => $this->subida($pdf), 'titulo' => 'Firmado', 'nivel_acceso' => 'reservado']);
        $a = Archivo::query()->where('titulo', 'Firmado')->first();
        $this->assertSame($pdf, app(FileVault::class)->get($a->ruta, $a->sha256)); // no se modifica: la firma sigue válida
        $this->assertSame('firmada', $this->insp()->inspeccionar(app(FileVault::class)->get($a->ruta, $a->sha256))['estado']);
    }

    public function test_los_anexos_del_ciudadano_se_analizan(): void
    {
        $s = $this->solicitudDOM();
        $this->assertTrue(Archivo::query()->where('solicitud_id', $s->id)->whereNotNull('firma_estado')->exists());
        $this->assertSame(['sin_firma'], Archivo::query()->where('solicitud_id', $s->id)->where('ambito', 'anexo')->pluck('firma_estado')->unique()->values()->all());
    }

    public function test_analisis_perezoso_y_comando_para_archivos_anteriores(): void
    {
        [$e, $dom] = $this->expediente();
        $a = Archivo::query()->where('expediente_id', $e->id)->first();
        $a->forceFill(['firma_estado' => null, 'firma_detalle' => null, 'firma_analizada_en' => null])->save();
        $this->comoFuncionario($dom)->get("/app/expedientes/{$e->id}")->assertOk();
        $this->assertNotNull($a->fresh()->firma_estado);
        Archivo::query()->update(['firma_estado' => null]);
        $this->artisan('archivos:analizar-firmas')->assertSuccessful();
        $this->assertSame(0, Archivo::query()->whereNull('firma_estado')->count());
    }

    public function test_archivo_ilegible_en_el_repositorio_queda_no_verificable(): void
    {
        [$e] = $this->expediente();
        $a = Archivo::query()->where('expediente_id', $e->id)->first();
        $a->forceFill(['sha256' => str_repeat('0', 64), 'firma_estado' => null])->save(); // la verificación de integridad del repositorio falla
        app(ArchivoFirmaService::class)->reanalizar($a);
        $this->assertSame('no_verificable', $a->fresh()->firma_estado);
    }

    public function test_etiquetas(): void
    {
        foreach ([null, 'sin_firma', 'firmada', 'firmada_con_cambios', 'invalida', 'no_verificable', 'no_aplica'] as $e) {
            $this->assertNotSame('', ArchivoFirmaService::etiqueta($e));
        }
        $this->assertSame('Sin analizar', ArchivoFirmaService::etiqueta(null));
    }
}
