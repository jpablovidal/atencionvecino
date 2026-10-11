<?php

namespace Tests\Unit;

use App\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadValidatorTest extends TestCase
{
    private function pdf(string $medio = ''): string
    {
        return "%PDF-1.4\n1 0 obj<<>>endobj\n$medio\ntrailer<<>>\n%%EOF";
    }

    public function test_acepta_formatos_validos_por_contenido(): void
    {
        $this->assertSame('application/pdf', UploadValidator::validarBytes($this->pdf())['mime']);
        $this->assertSame('image/png', UploadValidator::validarBytes("\x89PNG\r\n\x1A\n".str_repeat("\0", 20))['mime']);
        $this->assertSame('image/jpeg', UploadValidator::validarBytes("\xFF\xD8\xFF\xE0".str_repeat("\0", 20))['mime']);
        $this->assertSame('image/tiff', UploadValidator::validarBytes("II*\x00".str_repeat("\0", 20))['mime']);
    }

    public static function maliciosos(): array
    {
        return [
            'ejecutable Windows' => ["MZ\x90\x00\x03\x00\x00\x00 programa"],
            'ELF' => ["\x7FELF\x02\x01\x01"],
            'script PHP' => ['<?php system($_GET["c"]); ?>'],
            'html' => ['<html><script>alert(1)</script></html>'],
            'zip/docm' => ["PK\x03\x04 contenido"],
            'svg' => ['<svg onload="alert(1)"></svg>'],
            'vacío' => [''],
            'pdf con /JavaScript' => ["%PDF-1.4\n1 0 obj<</S/JavaScript/JS(app.alert(1))>>endobj\n%%EOF"],
            'pdf con /JS' => ["%PDF-1.4\n1 0 obj<</JS (x)>>endobj\n%%EOF"],
            'pdf con /Launch' => ["%PDF-1.4\n1 0 obj<</S/Launch>>endobj\n%%EOF"],
            'pdf con /EmbeddedFile' => ["%PDF-1.4\n1 0 obj<</Type/EmbeddedFile>>endobj\n%%EOF"],
            'pdf con /RichMedia' => ["%PDF-1.4\n1 0 obj<</Subtype/RichMedia>>endobj\n%%EOF"],
            'pdf con /OpenAction' => ["%PDF-1.4\n1 0 obj<</OpenAction 2 0 R>>endobj\n%%EOF"],
            'pdf evasión con #hex' => ["%PDF-1.4\n1 0 obj<</S/J#61vaScript>>endobj\n%%EOF"],
            'pdf truncado' => ['%PDF-1.4 sin cierre'],
        ];
    }

    #[DataProvider('maliciosos')]
    public function test_rechaza_contenido_malicioso_aunque_se_disfrace(string $bytes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UploadValidator::validarBytes($bytes);
    }

    public function test_extension_y_content_type_declarados_no_importan(): void
    {
        $p = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($p, "MZ\x90\x00 malware");
        $f = new UploadedFile($p, 'inocente.pdf', 'application/pdf', null, true);
        $this->expectException(\InvalidArgumentException::class);
        UploadValidator::validar($f);
    }

    public function test_tamano_maximo(): void
    {
        $p = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($p, $this->pdf(str_repeat('A', 11 * 1024 * 1024)));
        $this->expectException(\InvalidArgumentException::class);
        UploadValidator::validar(new UploadedFile($p, 'grande.pdf', 'application/pdf', null, true));
    }
}
