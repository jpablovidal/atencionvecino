<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la API del Codificador (documento «Selecciona un servicio para consultar»):
 *   GET {base}/v{version}/codigos/{idCodificacion}?codigo&desde&hasta&padre&descripcion
 *   GET {base}/v{version}/v1/codigos/{idCodificacion}/{idCodigo}              (detalle)
 *   GET {base}/v{version}/v1/codigos/{idCodificacion}/{idCodigo}/jerarquia?tipovista&orden
 * Es de consulta pública (sin autenticación; se admite un token opcional). Respuesta: {"codigos":[{id,codigo,descripcion,fecha_incio|fecha_inicio,
 * fecha_fin,idCodificacion,info_extra (JSON en texto),padre,secuencia,fecha_cambio,identificador}]}.
 * A VALIDAR: el documento antepone «/v1» en detalle y jerarquía además de la versión; si el servidor responde 404 se reintenta sin ese segmento.
 */
class CodificadorApi
{
    public function base(): string
    {
        $b = rtrim((string) (config('sgd.codificador.api_url') ?: 'https://codificacion.digital.gob.cl/api'), '/');
        if (! str_starts_with($b, 'https://')) {
            throw new FlujoException('La URL de la API del Codificador debe ser https.');
        }

        return $b;
    }

    public function esOficial(): bool
    {
        $u = (string) config('sgd.codificador.api_url');

        return $u === '' || str_contains($u, 'codificacion.digital.gob.cl') || str_contains($u, 'codificador.digital.gob.cl');
    }

    /**
     * Estilo de rutas según el servidor. El documento «Selecciona un servicio para consultar» usa `https://codificacion.digital.gob.cl/api/v{version}/…`; la guía
     * de Postman del Codificador usa `https://codificador.digital.gob.cl/codigos/{id}` (sin `/api` ni versión). Se admiten ambos: «directo» si el servidor
     * configurado es codificador.digital.gob.cl. A VALIDAR cuál es el vigente.
     */
    public function directo(): bool
    {
        return str_contains((string) config('sgd.codificador.api_url'), 'codificador.digital.gob.cl');
    }

    private function prefijo(): string
    {
        return $this->directo() ? '' : '/'.$this->version();
    }

    private function version(): string
    {
        $v = (string) (config('sgd.codificador.version') ?: 'v1');

        return preg_match('/^v?\d{1,3}$/', $v) ? 'v'.ltrim($v, 'v') : 'v1';
    }

    private function idCodificacion(?int $id = null): int
    {
        return max(1, (int) ($id ?? config('sgd.codificador.id_codificacion', 1)));
    }

    private function get(string $ruta, array $query = [], ?string $alternativa = null): Response
    {
        $c = config('sgd.codificador');
        $pedir = fn (string $r) => Http::timeout((int) ($c['timeout_api'] ?? 60))->connectTimeout(5)->withoutRedirecting()->acceptJson()
            ->when(! empty($c['api_token']), fn ($h) => $h->withToken((string) $c['api_token']))->get($this->base().$r, array_filter($query, fn ($v) => $v !== null && $v !== ''));
        try {
            $r = $pedir($ruta);
            if ($r->status() === 404 && $alternativa !== null) {
                $r = $pedir($alternativa);
            }
        } catch (FlujoException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new FlujoException('No fue posible contactar el Codificador.');
        }
        if (! $r->successful()) {
            throw new FlujoException('El Codificador respondió HTTP '.$r->status().($r->status() === 404 ? ' (no encontrado)' : '').'.');
        }
        if (strlen($r->body()) > (int) ($c['max_bytes_api'] ?? 20 * 1024 * 1024)) {
            throw new FlujoException('La respuesta del Codificador es demasiado grande.');
        }

        return $r;
    }

    /** @return list<array<string,mixed>> */
    private function codigosDe(Response $r): array
    {
        $j = $r->json();
        $l = is_array($j) ? ($j['codigos'] ?? null) : null;
        if (! is_array($l)) {
            throw new FlujoException('Respuesta inválida del Codificador.');
        }

        return array_values(array_map(fn ($c) => $this->normalizar($c), array_filter($l, 'is_array')));
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function normalizar(array $c): array
    {
        $extra = $c['info_extra'] ?? null;
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }

        return [
            'id' => isset($c['id']) ? (int) $c['id'] : null, 'codigo' => (string) ($c['codigo'] ?? ''), 'descripcion' => (string) ($c['descripcion'] ?? ''),
            'fecha_inicio' => $c['fecha_inicio'] ?? $c['fecha_incio'] ?? null, 'fecha_fin' => $c['fecha_fin'] ?? null, 'idCodificacion' => $c['idCodificacion'] ?? $c['IdCodificacion'] ?? null,
            'info_extra' => is_array($extra) ? $extra : [], 'padre' => isset($c['padre']) ? (int) $c['padre'] : 0, 'secuencia' => $c['secuencia'] ?? null,
            'identificador' => (string) ($c['identificador'] ?? ''), 'hijos' => $c['hijos'] ?? $c['Hijos'] ?? null,
        ];
    }

    /**
     * Listado de códigos de una codificación, con filtros opcionales (codigo, desde, hasta, padre, descripcion).
     *
     * @param  array{codigo?:string,desde?:string,hasta?:string,padre?:int,descripcion?:string}  $filtros
     * @return list<array<string,mixed>>
     */
    public function codigos(array $filtros = [], ?int $idCodificacion = null): array
    {
        $q = array_intersect_key($filtros, array_flip(['codigo', 'desde', 'hasta', 'padre', 'descripcion']));

        return $this->codigosDe($this->get($this->prefijo().'/codigos/'.$this->idCodificacion($idCodificacion), $q));
    }

    /** @return array<string,mixed>|null */
    public function detalle(string $idCodigo, ?int $idCodificacion = null): ?array
    {
        $this->idSeguro($idCodigo);
        $id = $this->idCodificacion($idCodificacion);
        $v = $this->prefijo();
        $r = $this->get($this->directo() ? "$v/codigos/$id/".rawurlencode($idCodigo) : "$v/v1/codigos/$id/".rawurlencode($idCodigo), [], "$v/codigos/$id/".rawurlencode($idCodigo));

        return $this->codigosDe($r)[0] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function jerarquia(string $idCodigo, string $vista = 'jsonlist', string $orden = 'asc', ?int $idCodificacion = null): array
    {
        $this->idSeguro($idCodigo);
        $id = $this->idCodificacion($idCodificacion);
        $v = $this->prefijo();
        $q = ['tipovista' => in_array($vista, ['jsonlist', 'jsontree', 'html'], true) ? $vista : 'jsonlist', 'orden' => $orden === 'desc' ? 'desc' : 'asc'];
        $r = $this->get(($this->directo() ? "$v/codigos/$id/" : "$v/v1/codigos/$id/").rawurlencode($idCodigo).'/jerarquia', $q, "$v/codigos/$id/".rawurlencode($idCodigo).'/jerarquia');

        return $this->codigosDe($r);
    }

    private function idSeguro(string $v): void
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-]{0,59}$/', $v)) {
            throw new FlujoException('Identificador de código inválido.');
        }
    }
}
