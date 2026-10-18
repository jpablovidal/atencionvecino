<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\AuditoriaService;
use App\Services\ProveedorPagoService;
use App\Support\Run;
use Illuminate\Http\Request;

/** Consulta pública de la situación de pago de una factura (RUT de la empresa, número y fecha de la factura). */
class ProveedoresController extends Controller
{
    public function __construct(private readonly ProveedorPagoService $svc, private readonly AuditoriaService $audit) {}

    public function form()
    {
        return view('publico.pagos', ['resultado' => null, 'error' => null, 'entrada' => ['rut' => '', 'factura' => '', 'fecha' => '']]);
    }

    public function consultar(Request $r)
    {
        $d = $r->validate(['rut' => 'required|string|max:14', 'factura' => 'required|string|max:40', 'fecha' => 'required|string|max:10']);
        $resultado = null;
        $error = null;
        try {
            $resultado = $this->svc->consultar($d['rut'], $d['factura'], $d['fecha']);
        } catch (FlujoException $e) {
            $error = $e->getMessage();
        }
        $run = Run::normalizar($d['rut']);
        $this->audit->registrar('publico.pago_consulta', null, null, ['rut' => $run ? Run::enmascarar($run) : 'invalido', 'resultado' => $error ? 'datos_invalidos' : (count($resultado ?? []) ? 'encontrado' : 'sin_informacion')], false, ['run' => null, 'rol' => 'publico']);

        return response()->view('publico.pagos', ['resultado' => $resultado, 'error' => $error, 'entrada' => ['rut' => $d['rut'], 'factura' => $d['factura'], 'fecha' => $d['fecha']]])->header('Cache-Control', 'no-store');
    }
}
