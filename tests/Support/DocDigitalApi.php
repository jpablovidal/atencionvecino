<?php

namespace Tests\Support;

use App\Models\Institucion;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Simulaciones HTTP con la forma REAL de la API de DocDigital v3 (OpenAPI 3.2.514): BaseResponseV2 {status, message, result, …}. */
trait DocDigitalApi
{
    protected string $dd = 'https://demodoc.ejemplo.gob.cl/api';

    /** @return array<string,mixed> */
    protected function ddOk(mixed $result): array
    {
        return ['status' => 200, 'message' => 'OK', 'count' => is_array($result) ? count($result) : 1, 'result' => $result, 'total_count' => is_array($result) ? count($result) : 1, 'total_pages' => 1, 'page' => 0];
    }

    /** Configura el cliente para DocDigital (token fijo, entidad y tipo) y asocia las instituciones de prueba a su id de entidad. */
    protected function ddConfig(string $modo = 'demo', array $extra = []): void
    {
        config(array_merge(['sgd.docdigital.mode' => $modo, 'sgd.docdigital.url_demo' => $this->dd, 'sgd.docdigital.api_token' => 'tok-dd', 'sgd.docdigital.client_id' => null, 'sgd.docdigital.client_secret' => null,
            'sgd.docdigital.token_url' => null, 'sgd.docdigital.entidad_id' => 5001, 'sgd.docdigital.tipo_documento_id' => 3], $extra));
        foreach (['DEMO-GORE-VAL' => 7001, 'DEMO-SEGPRES' => 7002] as $codigo => $id) {
            Institucion::query()->where('codigo', $codigo)->update(['docdigital_entidad_id' => $id]);
        }
        $this->app->forgetInstance(DocDigitalClient::class);
        $this->app->forgetInstance(ComunicacionService::class);
    }

    /** @return array<string,mixed> un DocumentoResponse mínimo y válido según la especificación */
    protected function ddRecibido(int $id, string $asunto, int $entidad = 7001, string $nombre = 'Gobierno Regional de Valparaíso', array $anexos = []): array
    {
        return ['solicitud_id' => 9000 + $id, 'documento_principal' => ['id' => "A-$id", 'materia' => $asunto, 'documento_id' => $id, 'nombre_archivo' => "oficio-$id.pdf", 'tipo_id' => 3, 'tipo' => 'Oficio'],
            'documentos_anexos' => $anexos, 'info_creador' => ['usuario_id' => 1, 'usuario_run' => '1-9', 'usuario_nombre' => 'Persona Creadora', 'entidad_id' => $entidad, 'entidad_nombre' => $nombre],
            'tipo_tramitacion' => 'COMUNICACION_OFICIAL', 'estado_tramitacion' => 'OPS_RESUELTO_ENVIADO'];
    }

    /**
     * Simula todos los endpoints usados. $recibidos: [id => asunto]; $pdfs: [id => bytes] (por defecto un PDF válido).
     *
     * @param  array<int,string>  $recibidos
     * @param  array<int,string>  $pdfs
     * @param  array<string,mixed>  $extra  respuestas específicas (tienen prioridad)
     */
    protected function ddFake(array $recibidos = [], array $pdfs = [], array $extra = [], array $anexos = []): void
    {
        $lista = [];
        foreach ($recibidos as $id => $asunto) {
            $meta = [];
            foreach ($anexos[$id] ?? [] as $aid => $a) {
                $meta[] = ['id' => (string) $aid, 'correlativo' => count($meta) + 1, 'tipo' => $a['tipo'] ?? 'anexo', 'nombre_archivo' => $a['nombre'], 'url_descarga' => 'https://evil.example/'.$aid];
            }
            $lista[] = $this->ddRecibido($id, $asunto, 7001, 'Gobierno Regional de Valparaíso', $meta);
        }
        $base = $this->dd;
        Http::swap(new Factory); // cada llamada parte de cero: las simulaciones anteriores no pisan a las nuevas
        Http::fake($extra + [
            "$base/oauth/token" => Http::response(['access_token' => 'TOK-ABC', 'token_type' => 'Bearer', 'expires_in' => 3600], 200),
            "$base/entidades/token" => Http::response($this->ddOk([['entidad_id' => 5001, 'entidad_nombre' => 'I. Municipalidad de Santo Domingo', 'entidad_padre_id' => 1]]), 200),
            "$base/tipos/documentos/" => Http::response($this->ddOk([['tipo_id' => 3, 'nombre' => 'Oficio']]), 200),
            "$base/documentos/firmado/ingresar" => Http::response($this->ddOk(['fecha_ingreso' => '2026-10-19', 'id_documento' => 987654, 'id_solicitud' => 555, 'anexos' => []]), 200),
            "$base/documentos/recibidos/*/acusorecibo*" => Http::response($this->ddOk(['completada' => true, 'tareaSiguienteId' => 77]), 200),
            "$base/documentos/recibidos/*/devolver*" => Http::response($this->ddOk(['completada' => true, 'tareaSiguienteId' => 78]), 200),
            "$base/documentos/recibidos*" => Http::response($this->ddOk($lista), 200),
            "$base/documentos/*/archivo/descargar*" => function (Request $r) use ($pdfs, $anexos) {
                preg_match('#/documentos/(\d+)/archivo/descargar#', $r->url(), $m);
                parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
                if (isset($q['archivo_id'])) { // anexo
                    $a = $anexos[(int) ($m[1] ?? 0)][$q['archivo_id']] ?? null;

                    return $a === null || ! array_key_exists('bytes', $a) || $a['bytes'] === null ? Http::response('', 404) : Http::response($a['bytes'], 200);
                }

                return Http::response($pdfs[(int) ($m[1] ?? 0)] ?? "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n% recibido {$m[1]}\ntrailer<</Root 1 0 R>>\n%%EOF", 200);
            },
            "$base/documentos/*" => Http::response($this->ddOk(['solicitud_id' => 1, 'tipo_tramitacion' => 'COMUNICACION_OFICIAL', 'estado_tramitacion' => 'OPS_PENDIENTE_DESPACHO',
                'destinatarios' => ['reservado' => false, 'con_copia' => ['entidades' => [['entidad_id' => 7002, 'entidad_nombre' => 'SEGPRES', 'estado' => 'Pendiente acuse']]]]]), 200),
        ]);
    }
}
