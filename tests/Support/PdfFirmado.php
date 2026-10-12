<?php

namespace Tests\Support;

/** Genera PDF con firmas PKCS#7 desprendidas reales (certificado autofirmado de prueba) para probar el reconocimiento de firmas. */
class PdfFirmado
{
    /** @return array{cert:string,key:\OpenSSLAsymmetricKey,certPem:string} */
    public static function identidad(string $cn = 'Juan Pérez Soto', string $emisor = 'AC Prueba SGD', ?array $ca = null, int $dias = 365, int $desdeDias = 0): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $cn, 'organizationName' => 'Municipalidad de Prueba', 'serialNumber' => '12345678-5', 'countryName' => 'CL'], $key, ['digest_alg' => 'sha256']);
        $cacert = $ca['certPem'] ?? null;
        $cakey = $ca['key'] ?? $key;
        $x = openssl_csr_sign($csr, $cacert, $cakey, $dias, ['digest_alg' => 'sha256'], random_int(1000, 999999));
        openssl_x509_export($x, $pem);

        return ['cert' => $emisor, 'key' => $key, 'certPem' => $pem];
    }

    /** Construye un PDF y lo firma. $despues se agrega tras la firma (actualización incremental). */
    public static function crear(array $id, string $texto = 'Documento de prueba', ?string $despues = null, bool $alterar = false, string $subfiltro = 'adbe.pkcs7.detached'): string
    {
        $tam = 8192;
        $ph = '<'.str_repeat('0', $tam * 2).'>';
        $br = '/ByteRange [0 0000000000 0000000000 0000000000]';
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]/Contents 5 0 R>>endobj\n5 0 obj<</Length ".strlen($texto)." >>stream\n$texto\nendstream endobj\n"
            ."4 0 obj<</Type/Sig/Filter/Adobe.PPKLite/SubFilter/$subfiltro/M (D:20260315120000-03'00')/Reason (Aprobación)/Location (Santo Domingo)$br/Contents $ph>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";
        $posContents = strpos($pdf, $ph);
        $a = 0;
        $b = $posContents;
        $c = $posContents + strlen($ph);
        $d = strlen($pdf) - $c;
        $nuevoBr = sprintf('/ByteRange [0 %010d %010d %010d]', $b, $c, $d);
        $pdf = str_replace($br, $nuevoBr, $pdf);
        $firmado = substr($pdf, 0, $b).substr($pdf, $c);
        $in = tempnam(sys_get_temp_dir(), 'in');
        $out = tempnam(sys_get_temp_dir(), 'out');
        file_put_contents($in, $firmado);
        $ok = openssl_pkcs7_sign($in, $out, $id['certPem'], $id['key'], [], PKCS7_DETACHED | PKCS7_BINARY);
        $smime = (string) file_get_contents($out);
        @unlink($in);
        @unlink($out);
        if (! $ok || ! preg_match('/Content-Type: application\/(?:x-)?pkcs7-signature.*?\r?\n\r?\n(.*?)\r?\n------/s', $smime, $m)) {
            throw new \RuntimeException('No se pudo firmar el PDF de prueba');
        }
        $der = base64_decode(preg_replace('/\s+/', '', $m[1]));
        $hex = strtoupper(bin2hex($der));
        $pdf = substr($pdf, 0, $b + 1).str_pad($hex, $tam * 2, '0').substr($pdf, $c - 1);
        if ($alterar) {
            $pdf = str_replace($texto, strrev($texto), $pdf); // mismo largo: altera el contenido firmado
        }

        return $despues === null ? $pdf : $pdf."\n".$despues;
    }
}
