<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\PiseeConsulta;
use App\Models\PiseeServicio;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use App\Support\Run;
use Illuminate\Support\Facades\Http;

/**
 * Consumo de servicios del Estado a través de PISEE (nodo de interoperabilidad de la municipalidad).
 * El contrato de cada servicio (ruta, parámetros, esquema de respuesta) y los mecanismos de identificación del
 * consumidor son A VALIDAR con la Secretaría de Gobierno Digital: aquí son configurables y están en lista blanca.
 * Minimización: solo se consulta el RUN del solicitante del expediente, con finalidad obligatoria; el resultado se
 * guarda cifrado y NUNCA se escribe en la bitácora.
 */
class PiseeService
{
    public function __construct(private readonly AuditoriaService $audit, private readonly FileVault $vault) {}

    public function habilitado(): bool
    {
        return in_array(config('sgd.pisee.mode'), ['mock', 'api'], true);
    }

    public function consultar(Expediente $e, PiseeServicio $s, Usuario $u, string $finalidad): PiseeConsulta
    {
        if (! $this->habilitado()) {
            throw new FlujoException('La integración PISEE no está habilitada.');
        }
        if ($e->estado !== 'abierto') {
            throw new FlujoException('El expediente está cerrado.');
        }
        if (! $s->activo || ! $this->rutaValida($s->ruta) || ! in_array($s->metodo, ['GET', 'POST'], true)) {
            throw new FlujoException('Servicio PISEE no disponible.');
        }
        $finalidad = trim($finalidad);
        if (mb_strlen($finalidad) < 10 || mb_strlen($finalidad) > 255 || preg_match('/[\r\n]/', $finalidad)) {
            throw new FlujoException('Indique la finalidad de la consulta (10 a 255 caracteres, una línea).');
        }
        $run = Run::normalizar($e->solicitud?->run);
        if (! $run) {
            throw new FlujoException('El expediente no tiene un RUN válido de solicitante.');
        }
        try {
            [$status, $cuerpo] = config('sgd.pisee.mode') === 'mock' ? $this->simular($s, $run) : $this->llamar($s, $run);
            $ok = $status >= 200 && $status < 300 && $cuerpo !== null;
            $error = $ok ? null : 'El servicio respondió con error (HTTP '.$status.')';
        } catch (\Throwable) {
            [$status, $cuerpo, $ok, $error] = [null, null, false, 'No fue posible contactar el nodo PISEE'];
        }
        $archivoId = null;
        if ($ok) {
            $f = $this->vault->put((string) $cuerpo);
            $archivoId = Archivo::query()->create([
                'expediente_id' => $e->id, 'solicitud_id' => $e->solicitud_id, 'ambito' => 'pisee', 'nombre_original' => 'pisee-'.$s->codigo.'.json', 'mime' => 'application/json',
                'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => 'Consulta PISEE: '.$s->nombre, 'nivel_acceso' => 'reservado', 'sensible' => true, 'subido_por' => $u->run,
            ])->id;
        }
        $c = PiseeConsulta::query()->create([
            'expediente_id' => $e->id, 'servicio_id' => $s->id, 'usuario_id' => $u->id, 'run_consultado' => $run, 'finalidad' => $finalidad,
            'estado' => $ok ? 'ok' : 'error', 'http_status' => $status, 'error' => $error, 'archivo_id' => $archivoId,
        ]);
        $this->audit->registrar('pisee.consulta', 'expediente', $e->id, ['servicio' => $s->codigo, 'estado' => $c->estado, 'http' => $status], true);

        return $c;
    }

    /** Ruta relativa segura (sin esquema, host, `..`, `//` ni caracteres de control): evita SSRF. */
    public function rutaValida(string $ruta): bool
    {
        return (bool) preg_match('#^/[A-Za-z0-9._~\-]+(/[A-Za-z0-9._~\-{}]+)*$#', $ruta) && ! str_contains($ruta, '..');
    }

    /** TLS mutuo hacia el nodo PISEE: certificado de cliente y CA propios; la verificación del servidor nunca se desactiva. @return array<string,mixed> */
    public function opcionesTls(): array
    {
        $c = config('sgd.pisee');
        $opts = ['verify' => $c['ca'] ?: true];
        if ($c['cert'] && $c['key']) {
            $opts['cert'] = $c['cert'];
            $opts['ssl_key'] = $c['key'];
        }

        return $opts;
    }

    /** @return array{0:int,1:?string} */
    private function llamar(PiseeServicio $s, string $run): array
    {
        $c = config('sgd.pisee');
        $base = rtrim((string) $c['base_url'], '/');
        if (! str_starts_with($base, 'https://')) {
            throw new \RuntimeException('PISEE_BASE_URL debe ser https');
        }
        $opts = $this->opcionesTls();
        $params = $s->param_run ? [$s->param_run => Run::sinDv($run)] : [];
        $h = Http::withOptions($opts)->timeout((int) $c['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson();
        if ($c['header_cliente'] && $c['valor_cliente'] && preg_match('/^[A-Za-z0-9\-]{1,60}$/', (string) $c['header_cliente']) && ! preg_match('/[\r\n]/', (string) $c['valor_cliente'])) {
            $h = $h->withHeaders([$c['header_cliente'] => $c['valor_cliente']]);
        }
        $r = $s->metodo === 'POST' ? $h->asJson()->post($base.$s->ruta, $params) : $h->get($base.$s->ruta, $params);
        $body = strlen($r->body()) <= $c['max_bytes'] && is_array($r->json()) ? (string) json_encode($r->json(), JSON_UNESCAPED_UNICODE) : null;

        return [$r->status(), $body];
    }

    /** SOLO DESARROLLO: datos simulados, claramente marcados. */
    private function simular(PiseeServicio $s, string $run): array
    {
        return [200, (string) json_encode(['simulado' => true, 'aviso' => 'DATOS SIMULADOS – no provienen de PISEE', 'servicio' => $s->codigo, 'run' => $run], JSON_UNESCAPED_UNICODE)];
    }
}
