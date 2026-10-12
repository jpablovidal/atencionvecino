<?php

namespace App\Services\Firma;

use App\Support\Der;

/**
 * Reconoce y verifica firmas electrónicas incrustadas en PDF (PAdES / PKCS#7 desprendida), sin ejecutar programas externos.
 *
 * Verifica: (1) que el rango firmado (/ByteRange) sea coherente y cubra el archivo completo, (2) que el resumen del
 * contenido coincida con el firmado (messageDigest) y (3) la firma criptográfica con la clave del certificado del firmante.
 * Con un paquete de CA configurado (SGD_FIRMA_CA_BUNDLE) valida además la cadena de confianza. NO revisa revocación (CRL/OCSP)
 * ni determina si la firma es «avanzada» según la Ley 19.799: eso depende de la lista de prestadores acreditados (A VALIDAR).
 */
class FirmaPdfInspector
{
    public const MAX_FIRMAS = 20;

    private const HASH = ['1.3.14.3.2.26' => 'sha1', '2.16.840.1.101.3.4.2.4' => 'sha224', '2.16.840.1.101.3.4.2.1' => 'sha256',
        '2.16.840.1.101.3.4.2.2' => 'sha384', '2.16.840.1.101.3.4.2.3' => 'sha512'];

    private const SIG_HASH = ['1.2.840.113549.1.1.5' => 'sha1', '1.2.840.113549.1.1.14' => 'sha224', '1.2.840.113549.1.1.11' => 'sha256', '1.2.840.113549.1.1.12' => 'sha384',
        '1.2.840.113549.1.1.13' => 'sha512', '1.2.840.10045.4.1' => 'sha1', '1.2.840.10045.4.3.1' => 'sha224', '1.2.840.10045.4.3.2' => 'sha256', '1.2.840.10045.4.3.3' => 'sha384', '1.2.840.10045.4.3.4' => 'sha512'];

    /** @var array<string,string> */
    private const SUBFILTROS = ['adbe.pkcs7.detached' => 'PKCS#7 desprendida (PAdES básica)', 'ETSI.CAdES.detached' => 'CAdES desprendida (PAdES)',
        'adbe.pkcs7.sha1' => 'PKCS#7 SHA-1 (heredada)', 'adbe.x509.rsa_sha1' => 'X.509 RSA SHA-1 (heredada)', 'ETSI.RFC3161' => 'Sello de tiempo del documento'];

    public function __construct(private readonly ?string $caBundle = null) {}

    /**
     * @return array{estado:string,firmas:array<int,array<string,mixed>>,observaciones:array<int,string>,analizado_en:string}
     *                                                                                                                        estado: sin_firma | firmada | firmada_con_cambios | invalida | no_verificable
     */
    public function inspeccionar(string $pdf): array
    {
        $res = ['estado' => 'sin_firma', 'firmas' => [], 'observaciones' => [], 'analizado_en' => now()->toIso8601String()];
        if (! str_starts_with($pdf, '%PDF-') || ! str_contains($pdf, '/ByteRange')) {
            return $res;
        }
        $len = strlen($pdf);
        $n = preg_match_all('#/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]#', $pdf, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $vistas = [];
        foreach (array_slice($m, 0, self::MAX_FIRMAS) as $x) {
            $br = [(int) $x[1][0], (int) $x[2][0], (int) $x[3][0], (int) $x[4][0]];
            $clave = implode(',', $br);
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $f = $this->firma($pdf, $len, $br, (int) $x[0][1]);
            if ($f !== null) {
                $res['firmas'][] = $f;
            }
        }
        if ($n > self::MAX_FIRMAS) {
            $res['observaciones'][] = 'El archivo tiene más de '.self::MAX_FIRMAS.' firmas; solo se analizaron las primeras.';
        }
        if ($res['firmas'] === []) {
            return $res;
        }
        $res['estado'] = $this->estadoGlobal($res);

        return $res;
    }

    /** @param array<string,mixed> $res */
    private function estadoGlobal(array &$res): string
    {
        $firmas = array_filter($res['firmas'], fn ($f) => ($f['tipo'] ?? '') !== 'sello_tiempo');
        if ($firmas === []) {
            $res['observaciones'][] = 'Solo contiene sellos de tiempo, sin firma de persona.';

            return 'no_verificable';
        }
        foreach ($firmas as $f) {
            if ($f['integridad'] === false) {
                $res['observaciones'][] = 'Una firma no coincide con el contenido del documento: fue alterado después de firmarse o la firma es inválida.';

                return 'invalida';
            }
        }
        foreach ($firmas as $f) {
            if ($f['integridad'] === null) {
                return 'no_verificable';
            }
        }
        $ultima = end($firmas);
        if (! $ultima['cubre_todo']) {
            $res['observaciones'][] = 'Se agregó contenido después de la última firma (actualización incremental): revise los cambios.';

            return 'firmada_con_cambios';
        }

        return 'firmada';
    }

    /**
     * @param  array<int,int>  $br
     * @return array<string,mixed>|null
     */
    private function firma(string $pdf, int $len, array $br, int $posBr): ?array
    {
        [$a, $b, $c, $d] = $br;
        if ($a !== 0 || $b < 1 || $c < $a + $b + 2 || $c + $d > $len || $d < 0) {
            return null; // no es una firma válida (p. ej. texto que imita un ByteRange)
        }
        $hueco = substr($pdf, $a + $b, $c - ($a + $b));
        if ($hueco === '' || $hueco[0] !== '<' || substr($hueco, -1) !== '>') {
            return null;
        }
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($hueco, 1, -1)) ?? '';
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            return null;
        }
        $der = hex2bin($hex) ?: '';
        $der = rtrim($der, "\0"); // relleno de ceros del marcador de posición
        if ($der === '' || ord($der[0]) !== 0x30) {
            return null;
        }
        $dict = substr($pdf, max(0, $posBr - 1500), 3000);
        $sub = preg_match('#/SubFilter\s*/([A-Za-z0-9_.]+)#', $dict, $s) ? $s[1] : null;
        $f = [
            'subfiltro' => $sub, 'formato' => self::SUBFILTROS[$sub] ?? ($sub ?? 'desconocido'), 'tipo' => $sub === 'ETSI.RFC3161' ? 'sello_tiempo' : 'firma',
            'campo_fecha' => $this->fechaPdf($dict), 'razon' => $this->cadena($dict, 'Reason'), 'lugar' => $this->cadena($dict, 'Location'), 'nombre_campo' => $this->cadena($dict, 'Name'),
            'cubre_todo' => $this->cubreTodo($pdf, $len, $c + $d), 'integridad' => null, 'firmante' => null, 'sujeto' => null, 'emisor' => null, 'serie' => null,
            'valido_desde' => null, 'valido_hasta' => null, 'fecha_firma' => null, 'algoritmo' => null, 'cadena' => 'no_verificada', 'certificado_vigente_al_firmar' => null, 'errores' => [],
        ];
        if (in_array($sub, ['adbe.x509.rsa_sha1'], true)) {
            $f['errores'][] = 'Formato de firma heredado: no se puede verificar.';

            return $f;
        }
        try {
            $this->cms($der, $pdf, $br, $f);
        } catch (\Throwable $e) {
            $f['integridad'] = null;
            $f['errores'][] = 'No se pudo leer la estructura de la firma.';
        }

        return $f;
    }

    private function cubreTodo(string $pdf, int $len, int $finFirmado): bool
    {
        return $finFirmado >= $len || trim(substr($pdf, $finFirmado)) === '';
    }

    private function fechaPdf(string $dict): ?string
    {
        if (preg_match('#/M\s*\(D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?([Zz+\-])?(\d{2})?\'?(\d{2})?#', $dict, $m)) {
            return sprintf('%s-%s-%s %s:%s:%s %s', $m[1], $m[2], $m[3], $m[4] ?? '00', $m[5] ?? '00', $m[6] ?? '00', ($m[7] ?? 'Z').($m[8] ?? '').(isset($m[9]) ? ':'.$m[9] : ''));
        }

        return null;
    }

    private function cadena(string $dict, string $clave): ?string
    {
        if (preg_match('#/'.$clave.'\s*\(((?:[^()\\\\]|\\\\.){0,200})\)#s', $dict, $m)) {
            $v = trim(preg_replace('/[^\P{C}\n]+/u', '', stripcslashes($m[1])) ?? '');

            return $v === '' ? null : mb_substr($v, 0, 150);
        }

        return null;
    }

    /**
     * @param  array<int,int>  $br
     * @param  array<string,mixed>  $f
     */
    private function cms(string $der, string $pdf, array $br, array &$f): void
    {
        $ci = Der::nodo($der, 0);
        $h = Der::hijos($der, $ci);
        if (count($h) < 2 || Der::oid($der, $h[0]) !== '1.2.840.113549.1.7.2') {
            throw new \RuntimeException('No es SignedData');
        }
        $sd = Der::hijos($der, Der::hijos($der, $h[1])[0]);
        $certs = [];
        $signerInfos = null;
        foreach ($sd as $nodo) {
            if ($nodo['tag'] === 0xA0) {
                foreach (Der::hijos($der, $nodo) as $cn) {
                    if ($cn['tag'] === 0x30) {
                        $certs[] = Der::crudo($der, $cn);
                    }
                }
            } elseif ($nodo['tag'] === 0x31) {
                $signerInfos = $nodo;
            }
        }
        if ($signerInfos === null) {
            throw new \RuntimeException('Sin firmantes');
        }
        $si = Der::hijos($der, Der::hijos($der, $signerInfos)[0]);
        // SignerInfo: version, sid, digestAlgorithm, [0] signedAttrs?, signatureAlgorithm, signature, [1] unsignedAttrs?
        $i = 2;
        $digAlg = Der::oid($der, Der::hijos($der, $si[$i])[0]);
        $i++;
        $attrs = null;
        if ($si[$i]['tag'] === 0xA0) {
            $attrs = $si[$i];
            $i++;
        }
        $sigAlg = Der::oid($der, Der::hijos($der, $si[$i])[0]);
        $firma = Der::bytes($der, $si[$i + 1]);
        $hash = self::HASH[$digAlg] ?? null;
        if ($hash === null) {
            throw new \RuntimeException('Algoritmo de resumen no soportado');
        }
        $contenido = substr($pdf, $br[0], $br[1]).substr($pdf, $br[2], $br[3]);
        $resumen = hash($hash, $contenido, true);
        $f['algoritmo'] = strtoupper($hash);
        $digestOk = true;
        $datosFirmados = $contenido;
        if ($attrs !== null) {
            $mensaje = null;
            foreach (Der::hijos($der, $attrs) as $at) {
                $p = Der::hijos($der, $at);
                $oid = Der::oid($der, $p[0]);
                $val = Der::hijos($der, $p[1])[0] ?? null;
                if ($oid === '1.2.840.113549.1.9.4' && $val) {
                    $mensaje = Der::bytes($der, $val);
                } elseif ($oid === '1.2.840.113549.1.9.5' && $val) {
                    $ts = Der::fecha($der, $val);
                    $f['fecha_firma'] = $ts ? gmdate('Y-m-d H:i:s', $ts).' UTC' : null;
                    $f['_ts'] = $ts;
                }
            }
            $digestOk = $mensaje !== null && hash_equals($mensaje, $resumen);
            // la firma se calcula sobre signedAttrs re-etiquetado como SET (0x31)
            $datosFirmados = "\x31".substr(Der::crudo($der, $attrs), 1);
        }
        if (! $digestOk) {
            $f['integridad'] = false;
            $f['errores'][] = 'El resumen del contenido no coincide con el firmado.';
            $this->certificados($certs, $f, null);

            return;
        }
        if (($sigAlg === '1.2.840.113549.1.1.10')) { // RSASSA-PSS: openssl_verify no lo soporta
            $f['integridad'] = null;
            $f['errores'][] = 'Algoritmo RSA-PSS: el contenido coincide con el resumen firmado, pero la firma no pudo verificarse.';
            $this->certificados($certs, $f, null);

            return;
        }
        $algoFirma = self::SIG_HASH[$sigAlg] ?? $hash;
        $firmante = null;
        foreach ($certs as $idx => $cd) {
            $pub = @openssl_pkey_get_public(self::pem($cd));
            if ($pub && @openssl_verify($datosFirmados, $firma, $pub, $algoFirma) === 1) {
                $firmante = $idx;
                break;
            }
        }
        $f['integridad'] = $firmante !== null;
        if ($firmante === null) {
            $f['errores'][] = $certs === [] ? 'La firma no incluye el certificado del firmante.' : 'La firma criptográfica no es válida.';
            $f['integridad'] = $certs === [] ? null : false;
        }
        $this->certificados($certs, $f, $firmante);
    }

    /**
     * @param  array<int,string>  $certs
     * @param  array<string,mixed>  $f
     */
    private function certificados(array $certs, array &$f, ?int $idx): void
    {
        if ($idx === null || ! isset($certs[$idx])) {
            unset($f['_ts']);

            return;
        }
        $x = @openssl_x509_parse(self::pem($certs[$idx]), true);
        if ($x) {
            $s = $x['subject'] ?? [];
            $f['firmante'] = $this->limpio((string) ($s['CN'] ?? ''));
            $f['sujeto'] = $this->limpio(implode(', ', array_map(fn ($k, $v) => $k.'='.(is_array($v) ? implode('/', $v) : $v), array_keys($s), $s)));
            $f['emisor'] = $this->limpio((string) (($x['issuer']['CN'] ?? '') ?: ($x['issuer']['O'] ?? '')));
            $f['serie'] = strtoupper((string) ($x['serialNumberHex'] ?? ''));
            $f['valido_desde'] = isset($x['validFrom_time_t']) ? gmdate('Y-m-d', $x['validFrom_time_t']) : null;
            $f['valido_hasta'] = isset($x['validTo_time_t']) ? gmdate('Y-m-d', $x['validTo_time_t']) : null;
            $ts = $f['_ts'] ?? null;
            if ($ts && isset($x['validFrom_time_t'], $x['validTo_time_t'])) {
                $f['certificado_vigente_al_firmar'] = $ts >= $x['validFrom_time_t'] && $ts <= $x['validTo_time_t'];
            }
        }
        unset($f['_ts']);
        $f['cadena'] = $this->cadenaConfianza($certs, $idx);
    }

    /** @param array<int,string> $certs */
    private function cadenaConfianza(array $certs, int $idx): string
    {
        $bundle = $this->caBundle ?? (string) config('sgd.firma.ca_bundle');
        if ($bundle === '' || ! is_file($bundle)) {
            return 'no_verificada'; // sin paquete de CA configurado no se afirma confianza
        }
        $inter = tempnam(sys_get_temp_dir(), 'ic');
        file_put_contents($inter, implode("\n", array_map(fn ($c) => self::pem($c), $certs)));
        $r = @openssl_x509_checkpurpose(self::pem($certs[$idx]), X509_PURPOSE_ANY, [$bundle], $inter);
        @unlink($inter);

        return $r === true ? 'verificada' : 'no_confiable';
    }

    private function limpio(string $s): string
    {
        return mb_substr(trim(preg_replace('/\p{C}+/u', ' ', $s) ?? ''), 0, 250);
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
    }
}
