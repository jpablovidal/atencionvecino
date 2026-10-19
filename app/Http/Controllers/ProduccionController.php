<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\Admin\ProduccionService;
use App\Services\Admin\SimulacionService;
use App\Support\Ambiente;
use Illuminate\Http\Request;

/** Administrador: datos simulados para conocer el sistema y paso (irreversible) de pruebas a producción. */
class ProduccionController extends Controller
{
    public function __construct(private readonly ProduccionService $svc, private readonly SimulacionService $sim) {}

    private function totpOk(Request $r): bool
    {
        return (int) $r->session()->get('totp_ok') === (int) $r->user()->id;
    }

    public function index(Request $r)
    {
        $u = $r->user();
        $totp = $this->totpOk($r) || ! config('sgd.admin_2fa');
        $req = $this->svc->requisitos($u, $totp);

        return view('app.admin.produccion', ['requisitos' => $req, 'puede' => collect($req)->every(fn ($x) => $x['ok']), 'conteos' => $this->svc->conteos(), 'frase' => ProduccionService::FRASE,
            'registro' => ProduccionService::registro(), 'pruebas' => Ambiente::esPruebas(), 'simulada' => SimulacionService::yaCargada(), 'resultado' => session('resultado_sim')]);
    }

    public function simular(Request $r)
    {
        try {
            $log = $this->sim->cargar();
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Datos simulados cargados.')->with('resultado_sim', $log);
    }

    public function pasar(Request $r)
    {
        $d = $r->validate(['frase' => 'required|string|max:100', 'codigo' => 'required|string|max:20', 'respaldo' => 'nullable|boolean']);
        try {
            $res = $this->svc->pasarAProduccion($r->user(), $d['frase'], $d['codigo'], $this->totpOk($r) || ! config('sgd.admin_2fa'), ! empty($d['respaldo']));
        } catch (FlujoException $e) {
            return back()->withInput($r->except(['codigo']))->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.produccion')->with('ok', 'El sistema está en PRODUCCIÓN. Se borraron los procesos de prueba y se desactivaron '.$res['usuarios_demo_desactivados'].' usuario(s) de demostración.');
    }
}
