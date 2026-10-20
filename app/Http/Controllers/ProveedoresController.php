<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\AuditoriaService;
use App\Services\ProveedorPagoService;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Consulta de la situación de pago de una factura. Exige ingresar con ClaveÚnica (identifica a la PERSONA que consulta, que queda
 * registrada) y, además, los tres datos de la factura: RUT de la empresa, número y fecha. ClaveÚnica no acredita que la persona
 * represente a la empresa: esa relación no se verifica (ver docs/LIMITACIONES.md).
 */
class ProveedoresController extends Controller
{
    private const POR_HORA = 30; // consultas por persona y hora

    public function __construct(private readonly ProveedorPagoService $svc, private readonly AuditoriaService $audit) {}

    /** @return array{run:string,nombres:string,apellidos:string}|null */
    private function persona(Request $r): ?array
    {
        $c = $r->session()->get('ciudadano');

        return is_array($c) && ! empty($c['run']) ? $c : null;
    }

    private function vista(Request $r, ?array $resultado, ?string $error, array $entrada)
    {
        $p = $this->persona($r);

        return response()->view('publico.pagos', ['persona' => $p ? ['nombre' => trim(($p['nombres'] ?? '').' '.($p['apellidos'] ?? '')), 'run' => Run::enmascarar((string) $p['run'])] : null,
            'resultado' => $resultado, 'error' => $error, 'entrada' => $entrada])->header('Cache-Control', 'no-store, private');
    }

    public function form(Request $r)
    {
        return $this->vista($r, null, null, ['rut' => '', 'factura' => '', 'fecha' => '']);
    }

    /** Guarda el destino de retorno (fijo) y envía a ClaveÚnica. */
    public function ingresar(Request $r)
    {
        $r->session()->put('retorno_pago', ['exp' => time() + 1800]);

        return redirect()->route('login');
    }

    public function consultar(Request $r)
    {
        $p = $this->persona($r);
        if (! $p) {
            return redirect()->route('proveedores.pagos')->with('aviso', 'Para consultar debe ingresar con ClaveÚnica.');
        }
        $d = $r->validate(['rut' => 'required|string|max:14', 'factura' => 'required|string|max:40', 'fecha' => 'required|string|max:10']);
        $clave = 'pago-prov:'.hash('sha256', (string) $p['run']);
        if (RateLimiter::tooManyAttempts($clave, self::POR_HORA)) {
            return $this->vista($r, null, 'Alcanzó el límite de consultas por hora. Intente más tarde.', $d)->setStatusCode(429);
        }
        RateLimiter::hit($clave, 3600);
        $resultado = null;
        $error = null;
        try {
            $resultado = $this->svc->consultar($d['rut'], $d['factura'], $d['fecha']);
        } catch (FlujoException $e) {
            $error = $e->getMessage();
        }
        $run = Run::normalizar($d['rut']);
        $this->audit->registrar('publico.pago_consulta', null, null, ['rut' => $run ? Run::enmascarar($run) : 'invalido', 'resultado' => $error ? 'datos_invalidos' : (count($resultado ?? []) ? 'encontrado' : 'sin_informacion')], false, ['run' => $p['run'], 'rol' => 'proveedor']);

        return $this->vista($r, $resultado, $error, ['rut' => $d['rut'], 'factura' => $d['factura'], 'fecha' => $d['fecha']]);
    }
}
