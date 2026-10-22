<?php

namespace App\Http\Controllers;

use App\Models\DocumentoPaso;
use App\Models\Solicitud;
use App\Support\Espacio;
use Illuminate\Http\Request;

/** Inicio dual: quien es funcionario(a) y también vecino(a) elige en qué espacio trabaja. */
class EspacioController extends Controller
{
    public function inicio(Request $r)
    {
        $u = $r->user();
        $c = $r->session()->get('ciudadano');
        if (! $c) {
            return redirect()->route('ingreso');
        }
        if (! $u || ! Espacio::dual()) { // vecino(a) sin cuenta de funcionario, o inicio dual desactivado
            return redirect()->route($u ? 'app.bandeja' : 'ciudadano.index');
        }
        $r->session()->forget('espacio');

        $sol = Solicitud::query()->where('run', $c['run'])->get(['estado']);
        $pend = 0;
        $porAsignar = 0;
        if ($u->operaExpedientes()) {
            $pend = DocumentoPaso::query()->where('usuario_id', $u->id)->where('estado', 'pendiente')->whereHas('documento', fn ($d) => $d->where('estado', 'en_flujo'))->count();
            $porAsignar = Solicitud::query()->where('estado', 'ingresada')->whereNull('expediente_id')->where('run', '!=', $u->run)->where(fn ($w) => $w->whereNull('interesado_run')->orWhere('interesado_run', '!=', $u->run))
                ->whereHas('tramite.unidades', fn ($q) => $q->where('unidades.id', $u->unidad_id))->count();
        }

        return view('espacio', [
            'u' => $u, 'c' => $c, 'pendientes' => $pend, 'porAsignar' => $porAsignar, 'opera' => $u->operaExpedientes(),
            'devueltas' => $sol->where('estado', 'devuelta')->count(), 'enCurso' => $sol->whereNotIn('estado', ['devuelta', 'finalizada', 'cerrada'])->count(),
        ]);
    }
}
