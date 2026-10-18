<?php

namespace App\Http\Controllers;

use App\Support\ManualRenderer;

class AyudaController extends Controller
{
    public function indice()
    {
        // Sugerencia: el manual del perfil de la persona que inició sesión.
        $u = auth()->user();
        $mio = session('ciudadano') ? 'ciudadano' : ($u ? ['funcionario' => 'funcionario', 'secretario' => 'secretario', 'alcalde' => 'alcalde', 'admin' => 'administrador', 'auditor' => 'auditor', 'autoridad' => 'autoridad', 'oirs' => 'oirs', 'transparencia' => 'transparencia', 'oficina_partes' => 'oficina-partes', 'contabilidad' => 'pagos', 'tesorero' => 'tesorero'][$u->rolBase()] ?? null : null);

        return view('ayuda.indice', ['perfiles' => ManualRenderer::PERFILES, 'mio' => $mio]);
    }

    public function manual(string $perfil, ManualRenderer $r)
    {
        $m = $r->render($perfil);
        abort_if($m === null, 404);

        return view('ayuda.manual', ['m' => $m, 'perfil' => $perfil, 'nombre' => ManualRenderer::PERFILES[$perfil][0]]);
    }

    public function accesibilidad()
    {
        return view('ayuda.accesibilidad', ['contacto' => config('sgd.accesibilidad_contacto')]);
    }
}
