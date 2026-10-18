<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Expediente;
use App\Models\ExpedienteVinculo;
use App\Services\ExpedienteVinculoService;
use Illuminate\Http\Request;

/** Buscar otros expedientes y microformas, asociarlos a un expediente e incorporar copias de su contenido. */
class VinculosController extends Controller
{
    public function __construct(private readonly ExpedienteVinculoService $svc) {}

    private function hecho(callable $fn, string|\Closure $ok)
    {
        try {
            $r = $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok instanceof \Closure ? $ok($r) : $ok);
    }

    public function buscar(Request $r, Expediente $expediente)
    {
        $this->authorize('operar', $expediente);
        $f = $r->validate(['q' => 'nullable|string|max:80', 'tipo' => 'nullable|in:expedientes,microformas']);
        $q = trim((string) ($f['q'] ?? ''));
        $u = $r->user();
        $tipo = $f['tipo'] ?? 'expedientes';

        return view('app.vinculos.buscar', ['e' => $expediente, 'q' => $q, 'tipo' => $tipo, 'resultados' => $tipo === 'microformas' ? $this->svc->buscarMicroformas($u, $expediente, $q) : $this->svc->buscarExpedientes($u, $expediente, $q),
            'puedeMicro' => $u->tienePermiso('archivo.ver')]);
    }

    public function vincular(Request $r, Expediente $expediente)
    {
        $d = $r->validate(['tipo' => 'required|in:expediente,microforma', 'id' => 'required|integer|min:1', 'motivo' => 'required|string|max:400']);

        return $this->hecho(fn () => $d['tipo'] === 'microforma' ? $this->svc->vincularMicroforma($r->user(), $expediente, (int) $d['id'], $d['motivo']) : $this->svc->vincularExpediente($r->user(), $expediente, (int) $d['id'], $d['motivo']),
            'Asociado al expediente. Abra el contenido para incorporar copias de lo que necesite.');
    }

    public function ver(Request $r, ExpedienteVinculo $vinculo)
    {
        $this->authorize('ver', $vinculo->expediente);
        $vinculo->load('expediente', 'origen', 'microforma', 'creador');

        return view('app.vinculos.ver', ['v' => $vinculo, 'c' => $this->svc->contenido($r->user(), $vinculo), 'puedeOperar' => $r->user()->can('operar', $vinculo->expediente) && $vinculo->expediente->estado === 'abierto']);
    }

    public function incorporar(Request $r, ExpedienteVinculo $vinculo)
    {
        $d = $r->validate(['documentos' => 'nullable|array|max:50', 'documentos.*' => 'integer', 'archivos' => 'nullable|array|max:50', 'archivos.*' => 'integer']);

        return $this->hecho(fn () => $this->svc->incorporar($r->user(), $vinculo, $d['documentos'] ?? [], $d['archivos'] ?? []), fn ($n) => "Se incorporaron $n archivo(s) al expediente, con su origen y huella SHA-256.");
    }

    public function quitar(Request $r, ExpedienteVinculo $vinculo)
    {
        $exp = $vinculo->expediente_id;
        try {
            $this->svc->desvincular($r->user(), $vinculo);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('app.expediente', $exp)->with('ok', 'Asociación quitada. Las copias ya incorporadas se conservan en el expediente.');
    }
}
