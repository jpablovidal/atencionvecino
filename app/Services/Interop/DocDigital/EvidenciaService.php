<?php

namespace App\Services\Interop\DocDigital;

use App\Models\ConfigSitio;
use App\Models\DocDigitalEvidencia;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Registro opcional del JSON de cada llamada a DocDigital, para armar el documento de evidencias que pide Gobierno Digital.
 * Se activa por tiempo limitado (por defecto 24 h). Nunca guarda cabeceras (Authorization), el token de acceso ni el contenido
 * de archivos: los campos base64 se reemplazan por su tamaño y huella SHA-256.
 */
class EvidenciaService
{
    public const CLAVE = 'docdigital_evidencia_hasta';

    private const MAX_CUERPO = 200_000;

    private static ?bool $activoCache = null;

    public static function activaHasta(): ?\DateTimeInterface
    {
        try {
            $v = ConfigSitio::query()->where('clave', self::CLAVE)->value('valor');
        } catch (\Throwable) {
            return null;
        }
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            $d = new \DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }

        return $d > new \DateTimeImmutable ? $d : null;
    }

    public static function activa(): bool
    {
        return self::$activoCache ??= self::activaHasta() !== null;
    }

    public static function olvidar(): void
    {
        self::$activoCache = null;
    }

    public static function activar(int $horas = 24, ?int $usuarioId = null): \DateTimeInterface
    {
        $hasta = (new \DateTimeImmutable)->modify('+'.max(1, min(72, $horas)).' hours');
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => $hasta->format('Y-m-d H:i:s'), 'actualizado_por' => $usuarioId]);
        self::olvidar();

        return $hasta;
    }

    public static function desactivar(?int $usuarioId = null): void
    {
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => '', 'actualizado_por' => $usuarioId]);
        self::olvidar();
    }

    /** Middleware de Guzzle: registra solicitud y respuesta (o el error de red) cuando la evidencia está activa. */
    public static function middleware(string $ambiente, bool $conCuerpoRespuesta = true): callable
    {
        return fn (callable $handler) => function (RequestInterface $req, array $opts) use ($handler, $ambiente, $conCuerpoRespuesta) {
            if (! self::activa()) {
                return $handler($req, $opts);
            }
            $t0 = microtime(true);
            $cuerpoReq = (string) $req->getBody();
            $req->getBody()->rewind();

            return $handler($req, $opts)->then(
                function (ResponseInterface $res) use ($req, $cuerpoReq, $ambiente, $t0, $conCuerpoRespuesta) {
                    $b = $conCuerpoRespuesta ? (string) $res->getBody() : '';
                    $res->getBody()->rewind();
                    self::registrar($ambiente, $req, $cuerpoReq, $res->getStatusCode(), $conCuerpoRespuesta ? $b : '[contenido binario no registrado; '.($res->getHeaderLine('Content-Length') ?: 'tamaño desconocido').' bytes]', $t0);

                    return $res;
                },
                function ($e) use ($req, $cuerpoReq, $ambiente, $t0) {
                    self::registrar($ambiente, $req, $cuerpoReq, null, 'Error de red: '.class_basename($e), $t0);

                    return Create::rejectionFor($e);
                }
            );
        };
    }

    private static function registrar(string $ambiente, RequestInterface $req, string $cuerpoReq, ?int $status, string $cuerpoRes, float $t0): void
    {
        try {
            DocDigitalEvidencia::query()->create([
                'creado_en' => now(),
                'ambiente' => $ambiente,
                'metodo' => strtoupper($req->getMethod()),
                'ruta' => mb_substr((string) preg_replace('/^.*?\/api(?=\/)/', '', $req->getUri()->getPath()) ?: $req->getUri()->getPath(), 0, 255),
                'consulta' => ($q = $req->getUri()->getQuery()) !== '' ? mb_substr($q, 0, 1000) : null,
                'solicitud' => $cuerpoReq === '' ? null : self::limpiar($cuerpoReq),
                'http_status' => $status,
                'respuesta' => $cuerpoRes === '' ? null : self::limpiar($cuerpoRes),
                'duracion_ms' => (int) round((microtime(true) - $t0) * 1000),
                'usuario_id' => auth()->id(),
            ]);
        } catch (\Throwable) {
            // la evidencia nunca debe romper la operación
        }
    }

    /** JSON legible y sin secretos ni archivos; el texto plano se recorta. */
    public static function limpiar(string $cuerpo): string
    {
        $j = json_decode($cuerpo, true);
        if (is_array($j)) {
            $s = json_encode(self::depurar($j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '';
        } else {
            $s = trim(strip_tags($cuerpo));
        }

        return mb_substr($s, 0, self::MAX_CUERPO);
    }

    /** @param array<mixed> $a */
    private static function depurar(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = self::depurar($v);
            } elseif (is_string($k) && in_array(strtolower($k), ['access_token', 'refresh_token', 'token', 'client_secret', 'password'], true)) {
                $a[$k] = '[oculto]';
            } elseif (is_string($v) && is_string($k) && in_array($k, ['documento', 'contenido'], true)) {
                $bin = base64_decode($v, true);
                $a[$k] = $bin === false ? '[contenido omitido: '.strlen($v).' caracteres]' : '[base64 omitido: '.strlen($bin).' bytes, sha256 '.hash('sha256', $bin).']';
            }
        }

        return $a;
    }

    /** @return array<int,array<string,mixed>> */
    public static function exportar(): array
    {
        return DocDigitalEvidencia::query()->orderBy('id')->get()->map(function ($e) {
            return [
                'n' => $e->id, 'fecha' => $e->creado_en, 'ambiente' => $e->ambiente, 'metodo' => $e->metodo, 'ruta' => $e->ruta, 'consulta' => $e->consulta,
                'solicitud' => self::comoJson($e->solicitud), 'http_status' => $e->http_status, 'respuesta' => self::comoJson($e->respuesta), 'duracion_ms' => $e->duracion_ms,
            ];
        })->all();
    }

    private static function comoJson(?string $s): mixed
    {
        if ($s === null) {
            return null;
        }
        $j = json_decode($s, true);

        return $j === null ? $s : $j;
    }

    public static function vaciar(): int
    {
        return DocDigitalEvidencia::query()->delete();
    }
}
