<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\Admin\AparienciaService;
use Illuminate\Http\Request;

class AparienciaController extends Controller
{
    public function __construct(private readonly AparienciaService $svc) {}

    public function index()
    {
        return view('app.admin.apariencia', ['a' => $this->svc->datos(), 'defecto' => AparienciaService::DEFECTO, 'tieneLogo' => $this->svc->logo() !== null]);
    }

    public function guardar(Request $r)
    {
        $d = $r->validate([
            'colores' => 'required|array', 'colores.*' => 'required|string|max:7', 'header' => 'required|array', 'header.titulo' => 'required|string|max:120', 'header.subtitulo' => 'nullable|string|max:120',
            'header.logo_alt' => 'nullable|string|max:150', 'header.logo_alto_px' => 'nullable|integer|between:24,120', 'footer' => 'nullable|array', 'footer.texto' => 'nullable|string|max:300', 'footer.texto_legal' => 'nullable|string|max:400',
            'footer.enlaces' => 'nullable|array|max:8', 'footer.enlaces.*.etiqueta' => 'nullable|string|max:60', 'footer.enlaces.*.url' => 'nullable|string|max:255',
        ]);
        $d['header']['mostrar_logo'] = $r->boolean('header.mostrar_logo');
        try {
            $this->svc->guardar($d, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Apariencia guardada.');
    }

    public function logo(Request $r)
    {
        $r->validate(['logo' => 'required|file|max:1024']);
        try {
            $this->svc->subirLogo($r->file('logo'), $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Logo actualizado.');
    }

    public function quitarLogo(Request $r)
    {
        $this->svc->quitarLogo($r->user());

        return back()->with('ok', 'Logo eliminado.');
    }

    public function restablecer(Request $r)
    {
        $this->svc->restablecer($r->user());

        return back()->with('ok', 'Se restablecieron los colores, el encabezado y el pie a los valores originales.');
    }
}
