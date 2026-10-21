<?php

namespace App\Http\Controllers;

use App\Support\Espacio;
use App\Support\ManualRenderer;

class AyudaController extends Controller
{
    /** Perfil activo: vecino(a) (sesión de ClaveÚnica sin cuenta de funcionario o espacio vecino) o funcionario(a). */
    private function esVecino(): bool
    {
        return (bool) (Espacio::dual() ? Espacio::activo() === Espacio::CIUDADANO : (session('ciudadano') && ! auth()->check()));
    }

    public function indice()
    {
        $u = auth()->user();
        $vecino = (bool) session('ciudadano') && (! $u || Espacio::dual());
        $permitidos = ManualRenderer::perfilesPara($u, $vecino);
        $perfiles = array_intersect_key(ManualRenderer::PERFILES, array_flip($permitidos));
        $mio = ManualRenderer::principalPara($u, $this->esVecino());

        return view('ayuda.indice', ['perfiles' => $perfiles, 'mio' => isset($perfiles[$mio]) ? $mio : null, 'todos' => count($perfiles) === count(ManualRenderer::PERFILES)]);
    }

    public function manual(string $perfil, ManualRenderer $r)
    {
        $u = auth()->user();
        if (! in_array($perfil, ManualRenderer::perfilesPara($u, (bool) session('ciudadano') && (! $u || Espacio::dual())), true) && isset(ManualRenderer::PERFILES[$perfil])) {
            return redirect()->route('ayuda')->with('error', 'Ese manual corresponde a otro perfil. Aquí están los manuales de su perfil.');
        }
        $m = $r->render($perfil);
        abort_if($m === null, 404);

        return view('ayuda.manual', ['m' => $m, 'perfil' => $perfil, 'nombre' => ManualRenderer::PERFILES[$perfil][0]]);
    }

    public function accesibilidad()
    {
        return view('ayuda.accesibilidad', ['contacto' => config('sgd.accesibilidad_contacto')]);
    }

    /** Resumen de la Política de Privacidad y Protección de Datos Personales (Decreto Alcaldicio N° 2590). */
    public function privacidad()
    {
        return view('ayuda.privacidad');
    }
}
