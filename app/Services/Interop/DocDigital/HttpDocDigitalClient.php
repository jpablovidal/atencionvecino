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

    /** Tamaño máximo de cada anexo según la especificación (50 MB). */
    public const MAX_ANEXO = 50 * 1024 * 1024;

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

    /**
     * Mensaje corto y seguro. DocDigital responde errores como JSON estándar {status, message, errorCode, error} o como TEXTO PLANO
     * (p. ej. 400 «No existe documento 60088 en estado pendiente para dar acuse.»). El 403 trae el RUN del usuario de la integración:
     * no se reproduce, se explica.
     */
    private function detalle(Response $r): string
    {
        if ($r->status() === 403) {
            return 'HTTP 403: el usuario de la integración no tiene permiso para esta operación en DocDigital (pida a Gobierno Digital que se lo otorgue)';
        }
        $m = $r->json('error') ?: $r->json('message');
        if (! is_string($m) || $m === '' || $m === 'OK') {
            $cuerpo = trim((string) $r->body());
            $m = $r->json() === null && $cuerpo !== '' && ! str_starts_with($cuerpo, '<') ? $cuerpo : null;
        }

        return 'HTTP '.$r->status().(is_string($m) && $m !== '' ? ': '.mb_substr(preg_replace('/[\r\n]+/', ' ', strip_tags($m)) ?? '', 0, 120) : '');
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
        $anexos = (array) ($meta['anexos'] ?? []);
        if (count($anexos) > $this->maxAnexos()) {
            throw new DocDigitalException('Demasiados anexos: el máximo por comunicación es '.$this->maxAnexos().'.');
        }
        foreach ($anexos as $a) {
            if (strlen((string) $a['bytes']) > min(self::MAX_ANEXO, (int) ($this->cfg['max_bytes_anexo'] ?? self::MAX_ANEXO))) {
                throw new DocDigitalException('El anexo «'.mb_substr((string) $a['nombre'], 0, 60).'» excede el tamaño máximo permitido por DocDigital (50 MB).');
            }
        }
        if ($anexos) {
            $cuerpo['documentos_anexos'] = array_map(fn ($a) => ['nombre_archivo' => mb_substr((string) $a['nombre'], 0, 255), 'tipo' => 'anexo', 'contenido' => base64_encode((string) $a['bytes'])], $anexos);
        }
        $r = $this->llamar(fn () => $this->http()->asJson()->post($base.self::P_INGRESAR, $cuerpo));
        $id = $r->successful() ? $r->json('result.id_documento') : null;
        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            throw new DocDigitalException('DocDigital rechazó el envío ('.$this->detalle($r).').');
        }
        $sol = $r->json('result.id_solicitud');

        $ax = [];
        foreach ((array) $r->json('result.anexos', []) as $a) {
            if (is_array($a) && isset($a['nombre_anexo'])) {
                $ax[] = ['id' => isset($a['id_documento_anexo']) && is_scalar($a['id_documento_anexo']) ? mb_substr((string) $a['id_documento_anexo'], 0, 100) : null, 'nombre' => mb_substr((string) $a['nombre_anexo'], 0, 190)];
            }
        }

        return ['id' => (string) $id, 'simulado' => false, 'solicitud_id' => is_scalar($sol) ? (string) $sol : null, 'anexos' => $ax];
    }

    /** Con varias entidades asociadas al token, el acuse y la devolución indican la entidad destinataria (si se configuró DOCDIGITAL_ENTIDAD_ID). */
    private function conEntidad(array $cuerpo): array|\stdClass
    {
        if ((int) ($this->cfg['entidad_id'] ?? 0) > 0) {
            $cuerpo['entidadDestinataria'] = (int) $this->cfg['entidad_id'];
        }

        return $cuerpo ?: new \stdClass;
    }

    public function estado(string $documentoId): ?array
    {
        $documentoId = $this->idSeguro($documentoId);
        $r = $this->llamar(fn () => $this->http()->get($this->base().'/documentos/'.$documentoId));
        if (! $r->successful()) {
            throw new DocDigitalException('No se pudo consultar el estado del documento ('.$this->detalle($r).').');
        }
        $est = $r->json('result.estado_tramitacion');
        if (! is_string($est) || ! preg_match('/^[A-Z_]{3,50}$/', $est)) {
            return null;
        }
        $dest = [];
        foreach ((array) $r->json('result.destinatarios.con_copia.entidades', []) as $d) {
            if (is_array($d) && isset($d['estado'])) {
                $dest[] = ['entidad_id' => isset($d['entidad_id']) && is_numeric($d['entidad_id']) ? (int) $d['entidad_id'] : null, 'nombre' => mb_substr((string) ($d['entidad_nombre'] ?? ''), 0, 190), 'estado' => mb_substr((string) $d['estado'], 0, 60)];
            }
        }

        return ['estado' => $est, 'destinatarios' => $dest];
    }

    public function maxAnexos(): int
    {
        return max(0, min(50, (int) ($this->cfg['max_anexos'] ?? 10)));
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
                    'origen_entidad_id' => isset($cr['entidad_id']) && is_numeric($cr['entidad_id']) ? (int) $cr['entidad_id'] : null, 'pdf' => $pdf, 'anexos' => $this->anexosDe($d)];
            }
            $paginas = (int) ($r->json('total_pages') ?? 1);
            if (count($docs) < self::PAGINA || $p + 1 >= $paginas) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $d @return list<array{id:string,nombre:string,tipo:string}> anexos informados en el documento recibido */
    private function anexosDe(array $d): array
    {
        $o = [];
        foreach ((array) ($d['documentos_anexos'] ?? []) as $a) {
            $id = is_array($a) ? ($a['id'] ?? $a['anexo_id'] ?? null) : null;
            if (is_scalar($id) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', (string) $id)) {
                $o[] = ['id' => (string) $id, 'nombre' => mb_substr((string) ($a['nombre_archivo'] ?? 'anexo'), 0, 190), 'tipo' => ($a['tipo'] ?? 'anexo') === 'url' ? 'url' : 'anexo'];
            }
        }

        return $o;
    }

    public function descargarAnexo(string $documentoId, string $anexoId): ?string
    {
        $documentoId = $this->idSeguro($documentoId);
        if (! preg_match('/^[A-Za-z0-9._-]{1,100}$/', $anexoId)) {
            throw new DocDigitalException('Identificador de anexo DocDigital inválido.');
        }

        return $this->descargar($documentoId, $anexoId, self::MAX_ANEXO);
    }

    /** GET /documentos/{id}/archivo/descargar (bytes del archivo principal o, con $anexoId, de un anexo). Null si no se pudo descargar. */
    private function descargar(string $id, ?string $anexoId = null, int $max = self::MAX_PDF): ?string
    {
        try {
            $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->withToken($this->token())->get($this->base().'/documentos/'.$id.'/archivo/descargar', $anexoId !== null ? ['archivo_id' => $anexoId] : []);
        } catch (DocDigitalException $e) {
            throw $e;
        } catch (\Throwable) {
            return null;
        }
        $b = $r->successful() ? $r->body() : '';

        return $b !== '' && strlen($b) <= $max ? $b : null;
    }
}
