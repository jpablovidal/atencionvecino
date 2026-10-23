<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Subrogancia;
use App\Models\Usuario;
use App\Services\SubroganciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Mantenedor de subrogancias: un funcionario(a) ausente es reemplazado entre dos fechas y horas. */
class SubroganciasController extends Controller
{
    public function __construct(private readonly SubroganciaService $svc) {}

    public function indice(Request $r)
    {
        $f = $r->validate(['estado' => 'nullable|in:vigente,programada,vencida,terminada']);
        $filas = Subrogancia::query()->with(['titular', 'subrogante'])->orderByDesc('desde')->limit(300)->get()
            ->filter(fn (Subrogancia $s) => empty($f['estado']) || $s->estado() === $f['estado'])->values();

        return view('app.subrogancias', [
            'filas' => $filas, 'f' => $f,
            'personas' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->orderBy('nombres')->get(),
        ]);
    }

    public function crear(Request $r)
    {
        $d = $r->validate([
            'titular_id' => 'required|integer|exists:usuarios,id', 'subrogante_id' => 'required|integer|exists:usuarios,id',
            'desde' => 'required|date_format:Y-m-d\TH:i', 'hasta' => 'required|date_format:Y-m-d\TH:i', 'motivo' => 'nullable|string|max:250',
        ]);
        try {
            $s = $this->svc->crear(Usuario::query()->findOrFail($d['titular_id']), Usuario::query()->findOrFail($d['subrogante_id']),
                Carbon::createFromFormat('Y-m-d\TH:i', $d['desde'], 'America/Santiago'), Carbon::createFromFormat('Y-m-d\TH:i', $d['hasta'], 'America/Santiago'), $d['motivo'] ?? null, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.subrogancias')->with('ok', 'Subrogancia registrada: '.$s->subrogante->nombreCompleto().' reemplaza a '.$s->titular->nombreCompleto().' del '.$s->desde->format('d-m-Y H:i').' al '.$s->hasta->format('d-m-Y H:i').'.');
    }

    public function terminar(Request $r, Subrogancia $subrogancia)
    {
        try {
            $this->svc->terminar($subrogancia, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Subrogancia terminada: el subrogante deja de ver esos expedientes desde ahora.');
    }
}
