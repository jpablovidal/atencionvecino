<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\ListaOpciones;
use App\Models\Unidad;
use App\Services\ListasOpcionesService;
use App\Services\TramiteService;
use Illuminate\Http\Request;

/** Listados de opciones reutilizables para los formularios de los trámites. */
class ListasOpcionesController extends Controller
{
    public function __construct(private readonly ListasOpcionesService $svc) {}

    public function index(Request $r)
    {
        $this->permiso('tramites.gestionar');

        return view('app.tramites.listas.index', ['listas' => ListaOpciones::query()->withCount('items')->with('unidad')->orderBy('nombre')->get(), 'usos' => $this->svc->usos(), 'u' => $r->user()]);
    }

    public function crear(Request $r)
    {
        $this->permiso('tramites.gestionar');

        return view('app.tramites.listas.form', ['lista' => null, 'texto' => '', 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(), 'admin' => TramiteService::veTodas($r->user()), 'puede' => true]);
    }

    public function guardar(Request $r)
    {
        $this->permiso('tramites.gestionar');
        try {
            $l = $this->svc->guardar($r->user(), $this->entrada($r));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.tramites.listas.editar', $l)->with('ok', 'Listado guardado. Ya puede usarlo en los campos de lista de cualquier trámite.');
    }

    public function editar(Request $r, ListaOpciones $lista)
    {
        $this->permiso('tramites.gestionar');

        return view('app.tramites.listas.form', ['lista' => $lista, 'texto' => ListasOpcionesService::aTexto($lista), 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(), 'admin' => TramiteService::veTodas($r->user()),
            'puede' => ListasOpcionesService::puedeEditar($r->user(), $lista), 'usos' => $this->svc->usos()[$lista->id] ?? 0]);
    }

    public function actualizar(Request $r, ListaOpciones $lista)
    {
        $this->permiso('tramites.gestionar');
        try {
            $this->svc->guardar($r->user(), $this->entrada($r), $lista);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.tramites.listas.editar', $lista)->with('ok', 'Listado actualizado. Los cambios se reflejan en los trámites que lo usan.');
    }

    public function estado(Request $r, ListaOpciones $lista)
    {
        $this->permiso('tramites.gestionar');
        try {
            $this->svc->activar($r->user(), $lista, $r->boolean('activa'));
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', $r->boolean('activa') ? 'Listado activado.' : 'Listado desactivado: ya no se ofrece para campos nuevos (los trámites que lo usan siguen funcionando).');
    }

    /** @return array<string,mixed> */
    private function entrada(Request $r): array
    {
        return $r->validate(['nombre' => 'required|string|max:300', 'descripcion' => 'nullable|string|max:600', 'opciones' => 'required|string|max:30000', 'unidad_id' => 'nullable|integer']);
    }
}
