<?php

namespace App\Services\Interop\DocDigital;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * DocDigital «API RESTful para el envío y recepción de documentos» (OpenAPI 3.1, versión 3.2.514; servidor DEMO
 * https://api-demodoc.digital.gob.cl/api). Autenticación: POST /oauth/token con HTTP Basic (Client_Id:Client_Secret) → JWT Bearer.
 * Rutas usadas (fijas, según la especificación): POST /documentos/firmado/ingresar · GET /documentos/recibidos ·
 * PUT /documentos/recibidos/{id}/acusorecibo · PUT /documentos/recibidos/{id}/devolver · GET /documentos/{id}/archivo/descargar ·
 * GET /entidades/token · GET /entidades/ · GET /tipos/documentos/.
 *
 * Verificado contra la especificación; NO probado contra el servicio real (la red de desarrollo no llega a DocDigital):
 * prueba en DemoDoc antes de producción. La URL de producción no figura en la especificación (DOCDIGITAL_URL_PROD, A VALIDAR).
 */
class HttpDocDigitalClient implements DocDigitalClient
{
    public const P_TOKEN = '/oauth/token';

    public const P_INGRESAR = '/documentos/firmado/ingresar';

    public const P_RECIBIDOS = '/documentos/recibidos';

    public const P_ENTIDADES_TOKEN = '/entidades/token';

    public const P_ENTIDADES = '/entidades/';

    public const P_TIPOS = '/tipos/documentos/';

    /** Tamaño máximo del archivo principal según la especificación (20 MB). */
    public const MAX_PDF = 20 * 1024 * 1024;

    private const PAGINA = 50;

    private const MAX_PAGINAS = 4;

    public function __construct(private readonly array $cfg, private readonly string $ambiente) {}

    private function urlBase(): string
    {
        return (string) ($this->ambiente === 'prod' ? $this->cfg['url_prod'] : $this->cfg['url_demo']);
    }

    private function base(): string
    {
        $u = $this->urlBase();
        if (! str_starts_with($u, 'https://') || ! $this->tieneCredenciales()) {
            throw new DocDigitalException('DocDigital no está configurado (URL https y credenciales).');
        }

        return rtrim($u, '/');
    }

    public function tieneCredenciales(): bool
    {
        return $this->usaOauth() || (bool) ($this->cfg['api_token'] ?? null);
    }

    private function usaOauth(): bool
    {
        return ! empty($this->cfg['client_id']) && ! empty($this->cfg['client_secret']);
    }

    private function urlToken(): string
    {
        $t = (string) ($this->cfg['token_url'] ?? '');

        return str_starts_with($t, 'https://') ? $t : $this->base().self::P_TOKEN;
    }

    /**
     * Token de acceso: POST /oauth/token con HTTP Basic (Client_Id:Client_Secret). Respuesta {access_token, token_type, expires_in}.
     * Se cachea hasta poco antes de vencer y nunca se escribe en registros ni en mensajes de error. Alternativa: token fijo.
     */
    public function token(): string
    {
        if (! $this->usaOauth()) {
            return (string) $this->cfg['api_token'];
        }
        $url = $this->urlToken();
        $clave = 'docdigital_token_'.hash('sha256', $url.'|'.$this->cfg['client_id'].'|'.$this->cfg['client_secret']);
        $t = Cache::get($clave);
        if (is_string($t) && $t !== '') {
            return $t;
        }
        try {
            $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()
                ->withBasicAuth((string) $this->cfg['client_id'], (string) $this->cfg['client_secret'])->send('POST', $url);
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar el servicio de autorización de DocDigital.');
        }
        $tok = $r->successful() ? $r->json('access_token') : null;
        if (! is_string($tok) || $tok === '') {
            throw new DocDigitalException('DocDigital no entregó un token de acceso (HTTP '.$r->status().'): revise el Client ID, el Client Secret y la URL.');
        }
        $ttl = max(30, min(3600, (int) ($r->json('expires_in') ?: 300) - 60));
        Cache::put($clave, $tok, $ttl);

        return $tok;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()->withToken($this->token());
    }

    /** Ejecuta la llamada y traduce fallas de red a un error sin detalles internos. */
    private function llamar(callable $f): Response
    {
        try {
            return $f();
        } catch (DocDigitalException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar DocDigital.');
        }
    }

    /** Mensaje corto y seguro del cuerpo de error estándar {status, message, errorCode, error}. */
    private function detalle(Response $r): string
    {
        $m = $r->json('error') ?: $r->json('message');

        return 'HTTP '.$r->status().(is_string($m) && $m !== '' && $m !== 'OK' ? ': '.mb_substr(preg_replace('/[\r\n]+/', ' ', $m) ?? '', 0, 100) : '');
    }

    /**
     * Diagnóstico sin datos: token, entidad asociada y tipos de documento (solo lectura).
     *
     * @return array<int,array{0:string,1:bool,2:string}>
     */
    public function diagnosticar(): array
    {
        $out = [];
        $u = $this->urlBase();
        $out[] = ['URL base (https)', str_starts_with($u, 'https://'), str_starts_with($u, 'https://') ? (string) parse_url($u, PHP_URL_HOST) : 'sin definir o no es https'];
        $out[] = ['Credenciales', $this->tieneCredenciales(), $this->usaOauth() ? 'OAuth2 (Client ID y Client Secret, HTTP Basic en /oauth/token)' : ($this->cfg['api_token'] ? 'token fijo' : 'sin definir')];
        if (! str_starts_with($u, 'https://') || ! $this->tieneCredenciales()) {
            return $out;
        }
        try {
            $this->token();
            $out[] = ['Autorización (token)', true, 'token obtenido'];
        } catch (DocDigitalException $e) {
            $out[] = ['Autorización (token)', false, $e->getMessage()];

            return $out;
        }
        try {
            $ent = $this->entidadesDelToken();
            $out[] = ['Entidad asociada al token', count($ent) > 0, count($ent) ? implode('; ', array_map(fn ($x) => $x['nombre'].' (id '.$x['id'].')', array_slice($ent, 0, 3))) : 'sin entidades'];
        } catch (DocDigitalException $e) {
            $out[] = ['Entidad asociada al token', false, $e->getMessage()];
        }
        try {
            $t = $this->tiposDocumento();
            $out[] = ['Tipos de documento', count($t) > 0, count($t).' disponibles'];
        } catch (DocDigitalException $e) {
            $out[] = ['Tipos de documento', false, $e->getMessage()];
        }
        try {
            $r = $this->llamar(fn () => $this->http()->get($this->base().self::P_RECIBIDOS, ['pageSize' => 1, 'pageNumber' => 0, 'notificado' => 'false']));
            $out[] = ['Consulta de recibidos', $r->successful(), $this->detalle($r)];
        } catch (DocDigitalException $e) {
            $out[] = ['Consulta de recibidos', false, $e->getMessage()];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function lista(Response $r): array
    {
        $res = $r->successful() ? $r->json('result') : null;
        if (! is_array($res)) {
            throw new DocDigitalException('Respuesta inválida de DocDigital ('.$this->detalle($r).').');
        }

        return array_values(array_filter($res, 'is_array'));
    }

    /** @return list<array{id:int,nombre:string,padre:?int}> entidades asociadas al token (GET /entidades/token) */
    public function entidadesDelToken(): array
    {
        $r = $this->llamar(fn () => $this->http()->get($this->base().self::P_ENTIDADES_TOKEN));

        return $this->entidades($this->lista($r));
    }

    /** @return list<array{id:int,nombre:string,padre:?int}> búsqueda de entidades por nombre (GET /entidades/) */
    public function buscarEntidades(string $nombre, int $max = 20): array
    {
        $r = $this->llamar(fn () => $this->http()->get($this->base().self::P_ENTIDADES, ['nombreEntidad' => $nombre, 'pageSize' => $max, 'pageNumber' => 0, 'orderType' => 'ASC']));

        return $this->entidades($this->lista($r));
    }

    /** @param list<array<string,mixed>> $f @return list<array{id:int,nombre:string,padre:?int}> */
    private function entidades(array $f): array
    {
        $o = [];
        foreach ($f as $e) {
            if (isset($e['entidad_id'], $e['entidad_nombre']) && is_numeric($e['entidad_id'])) {
                $o[] = ['id' => (int) $e['entidad_id'], 'nombre' => mb_substr((string) $e['entidad_nombre'], 0, 190), 'padre' => isset($e['entidad_padre_id']) && is_numeric($e['entidad_padre_id']) ? (int) $e['entidad_padre_id'] : null];
            }
        }

        return $o;
    }

    /** @return list<array{id:int,nombre:string}> GET /tipos/documentos/ */
    public function tiposDocumento(): array
    {
        $r = $this->llamar(fn () => $this->http()->get($this->base().self::P_TIPOS));
        $o = [];
        foreach ($this->lista($r) as $t) {
            if (isset($t['tipo_id'], $t['nombre']) && is_numeric($t['tipo_id'])) {
                $o[] = ['id' => (int) $t['tipo_id'], 'nombre' => mb_substr((string) $t['nombre'], 0, 120)];
            }
        }

        return $o;
    }

    /** Entidad creadora: DOCDIGITAL_ENTIDAD_ID o la única entidad asociada al token. */
    private function entidadPropia(): int
    {
        $id = (int) ($this->cfg['entidad_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $ent = Cache::remember('docdigital_entidad_propia_'.hash('sha256', (string) $this->cfg['client_id'].$this->urlBase()), 3600, fn () => $this->entidadesDelToken());
        if (count($ent) !== 1) {
            Cache::forget('docdigital_entidad_propia_'.hash('sha256', (string) $this->cfg['client_id'].$this->urlBase()));
            throw new DocDigitalException(count($ent) === 0 ? 'DocDigital no informa la entidad asociada al token.' : 'El token está asociado a varias entidades: indique DOCDIGITAL_ENTIDAD_ID.');
        }

        return $ent[0]['id'];
    }

    /** Entidad destinataria: id ya resuelto, código numérico, o coincidencia exacta y única de nombre en DocDigital. */
    private function entidadDestino(array $meta): int
    {
        if (! empty($meta['destino_entidad_id'])) {
            return (int) $meta['destino_entidad_id'];
        }
        $nombre = trim((string) ($meta['destino_nombre'] ?? ''));
        if ($nombre !== '') {
            $c = array_values(array_filter($this->buscarEntidades($nombre), fn ($e) => mb_strtolower($e['nombre']) === mb_strtolower($nombre)));
            if (count($c) === 1) {
                return $c[0]['id'];
            }
        }
        throw new DocDigitalException('No se pudo identificar la entidad destinataria en DocDigital: asocie la institución a su «id de entidad DocDigital» (Administración › Instituciones).');
    }

    /** Tipo de documento: el indicado, el configurado o el que coincida exactamente por nombre. */
    private function tipoId(array $meta): int
    {
        if (! empty($meta['tipo_id'])) {
            return (int) $meta['tipo_id'];
        }
        if ((int) ($this->cfg['tipo_documento_id'] ?? 0) > 0) {
            return (int) $this->cfg['tipo_documento_id'];
        }
        foreach ($this->tiposDocumento() as $t) {
            if (mb_strtolower($t['nombre']) === mb_strtolower((string) ($meta['tipo'] ?? ''))) {
                return $t['id'];
            }
        }
        throw new DocDigitalException('No hay un tipo de documento de DocDigital equivalente: indique DOCDIGITAL_TIPO_DOCUMENTO_ID (vea «Tipos de documento» en el diagnóstico).');
    }

    public function enviar(string $pdf, string $nombre, array $meta): array
    {
        if (strlen($pdf) > min(self::MAX_PDF, (int) $this->cfg['max_bytes'])) {
            throw new DocDigitalException('El documento excede el tamaño máximo permitido por DocDigital (20 MB).');
        }
        $base = $this->base();
        $reservado = (bool) ($meta['reservado'] ?? false);
        $cuerpo = [
            'tipo_id' => $this->tipoId($meta),
            'materia' => mb_substr((string) $meta['asunto'], 0, 150),
            'descripcion' => mb_substr((string) $meta['asunto'], 0, 250),
            'nombre' => mb_substr($nombre, 0, 255),
            'documento' => base64_encode($pdf),
            'reservado' => $reservado,
            'es_reservado' => $reservado,
            'id_entidad_creadora' => $this->entidadPropia(),
            'folio' => mb_substr((string) ($meta['numero'] ?: 'S/N'), 0, 100),
            'listado_id_entidades_destinatarias' => [$this->entidadDestino($meta)],
        ];
        $r = $this->llamar(fn () => $this->http()->asJson()->post($base.self::P_INGRESAR, $cuerpo));
        $id = $r->successful() ? $r->json('result.id_documento') : null;
        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            throw new DocDigitalException('DocDigital rechazó el envío ('.$this->detalle($r).').');
        }
        $sol = $r->json('result.id_solicitud');

        return ['id' => (string) $id, 'simulado' => false, 'solicitud_id' => is_scalar($sol) ? (string) $sol : null];
    }

    /** Con varias entidades asociadas al token, el acuse y la devolución indican la entidad destinataria (si se configuró DOCDIGITAL_ENTIDAD_ID). */
    private function conEntidad(array $cuerpo): array|\stdClass
    {
        if ((int) ($this->cfg['entidad_id'] ?? 0) > 0) {
            $cuerpo['entidadDestinataria'] = (int) $this->cfg['entidad_id'];
        }

        return $cuerpo ?: new \stdClass;
    }

    private function idSeguro(string $id): string
    {
        if (! preg_match('/^[0-9]{1,18}$/', $id)) {
            throw new DocDigitalException('Identificador de documento DocDigital inválido.');
        }

        return $id;
    }

    public function acusarRecibo(string $id): array
    {
        $id = $this->idSeguro($id);
        $r = $this->llamar(fn () => $this->http()->asJson()->put($this->base().self::P_RECIBIDOS.'/'.$id.'/acusorecibo', $this->conEntidad([])));
        if (! $r->successful()) {
            throw new DocDigitalException('DocDigital rechazó el acuse de recibo ('.$this->detalle($r).').');
        }
        $t = $r->json('result.tareaSiguienteId');

        return ['evidencia' => 'acuse-'.$id.(is_scalar($t) && (string) $t !== '' ? '-tarea-'.$t : ''), 'simulado' => false];
    }

    public function devolver(string $id, string $motivo): array
    {
        $id = $this->idSeguro($id);
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 255) {
            throw new DocDigitalException('El motivo de la devolución debe tener entre 3 y 255 caracteres.');
        }
        $r = $this->llamar(fn () => $this->http()->asJson()->put($this->base().self::P_RECIBIDOS.'/'.$id.'/devolver', $this->conEntidad(['motivo' => $motivo])));
        if (! $r->successful()) {
            throw new DocDigitalException('DocDigital rechazó la devolución ('.$this->detalle($r).').');
        }
        $t = $r->json('result.tareaSiguienteId');

        return ['evidencia' => 'devolucion-'.$id.(is_scalar($t) && (string) $t !== '' ? '-tarea-'.$t : ''), 'simulado' => false];
    }

    public function recibidos(): array
    {
        $base = $this->base();
        $out = [];
        for ($p = 0; $p < self::MAX_PAGINAS; $p++) {
            $r = $this->llamar(fn () => $this->http()->get($base.self::P_RECIBIDOS, ['notificado' => 'false', 'pageSize' => self::PAGINA, 'pageNumber' => $p, 'orderType' => 'ASC']));
            $docs = $this->lista($r);
            foreach ($docs as $d) {
                $princ = $d['documento_principal'] ?? null;
                $docId = is_array($princ) && isset($princ['documento_id']) && is_numeric($princ['documento_id']) ? (string) (int) $princ['documento_id'] : null;
                if ($docId === null) {
                    continue;
                }
                $pdf = $this->descargar($docId);
                if ($pdf === null) {
                    continue; // se reintenta en la próxima sincronización
                }
                $cr = is_array($d['info_creador'] ?? null) ? $d['info_creador'] : [];
                $out[] = ['id' => $docId, 'asunto' => mb_substr((string) ($princ['materia'] ?? $princ['descripcion'] ?? 'Documento recibido'), 0, 190),
                    'origen_codigo' => null, 'origen_nombre' => isset($cr['entidad_nombre']) ? mb_substr((string) $cr['entidad_nombre'], 0, 190) : null,
                    'origen_entidad_id' => isset($cr['entidad_id']) && is_numeric($cr['entidad_id']) ? (int) $cr['entidad_id'] : null, 'pdf' => $pdf];
            }
            $paginas = (int) ($r->json('total_pages') ?? 1);
            if (count($docs) < self::PAGINA || $p + 1 >= $paginas) {
                break;
            }
        }

        return $out;
    }

    /** GET /documentos/{id}/archivo/descargar (bytes del archivo principal). Null si no se pudo descargar. */
    private function descargar(string $id): ?string
    {
        try {
            $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->withToken($this->token())->get($this->base().'/documentos/'.$id.'/archivo/descargar');
        } catch (DocDigitalException $e) {
            throw $e;
        } catch (\Throwable) {
            return null;
        }
        $b = $r->successful() ? $r->body() : '';

        return $b !== '' && strlen($b) <= self::MAX_PDF ? $b : null;
    }
}
