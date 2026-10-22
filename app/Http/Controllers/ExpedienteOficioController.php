<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\TipoDocumento;
use App\Services\ExpedientePartesService;
use App\Services\ExpedienteService;
use App\Services\NotificacionPartesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Expedientes iniciados de oficio por un funcionario(a): crear, registrar sus partes y notificarlas al concluir el procedimiento. */
class ExpedienteOficioController extends Controller
{
    public function nuevo(Request $r)
    {
        $this->permiso('expedientes.crear');
        $puede = $r->user()->operaExpedientes() && $r->user()->unidad_id;

        return view('app.expediente_nuevo', ['puede' => (bool) $puede, 'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->pluck('nombre'), 'unidad' => $r->user()->unidad]);
    }

    public function crear(Request $r, ExpedienteService $svc)
    {
        $this->permiso('expedientes.crear');
        abort_unless($r->user()->operaExpedientes(), 403);
        $d = $r->validate(['asunto' => 'required|string|min:5|max:190', 'descripcion' => 'nullable|string|max:1000', 'resultado' => 'nullable|string|max:100', 'fundamento' => 'nullable|string|max:500']);
        try {
            $e = $svc->crearDeOficio($r->user(), $d);
        } catch (FlujoException $x) {
            return back()->withInput()->with('error', $x->getMessage());
        }

        return redirect()->route('app.expediente', $e)->with('ok', 'Expediente '.$e->codigo.' iniciado. Registre las partes, redacte los documentos y, al concluir, notifique.');
    }

    public function agregarParte(Request $r, Expediente $expediente, ExpedientePartesService $svc)
    {
        $this->permiso('expedientes.operar');
        $this->authorize('operar', $expediente);
        $d = $r->validate([
            'tipo' => ['required', Rule::in(array_keys(ExpedienteParte::TIPOS))], 'rol' => ['nullable', Rule::in(array_keys(ExpedienteParte::ROLES))], 'nombre' => 'nullable|string|max:190', 'run' => 'nullable|string|max:14',
            'correo' => 'nullable|string|max:190', 'usuario_id' => 'nullable|integer', 'dd_entidad_id' => 'nullable|integer|min:1', 'dd_usuarios' => 'nullable|array|max:20', 'dd_usuarios.*' => 'integer|min:1',
            'canal' => 'nullable|string|max:15', 'notas' => 'nullable|string|max:250',
        ]);
        try {
            $svc->agregar($expediente, $d, $r->user());
        } catch (FlujoException $x) {
            return back()->withInput()->with('error', $x->getMessage());
        }

        return back()->with('ok', 'Parte agregada al expediente.');
    }

    public function quitarParte(Request $r, Expediente $expediente, ExpedienteParte $parte, ExpedientePartesService $svc)
    {
        $this->permiso('expedientes.operar');
        $this->authorize('operar', $expediente);
        abort_unless($parte->expediente_id === $expediente->id, 404);
        try {
            $svc->quitar($parte, $r->user());
        } catch (FlujoException $x) {
            return back()->with('error', $x->getMessage());
        }

        return back()->with('ok', 'Parte quitada del expediente.');
    }

    public function notificar(Request $r, Expediente $expediente, NotificacionPartesService $svc)
    {
        $this->permiso('notificaciones.gestionar');
        $this->authorize('operar', $expediente);
        $d = $r->validate(['partes' => 'required|array|min:1|max:50', 'partes.*' => 'integer', 'documentos' => 'required|array|min:1|max:50', 'documentos.*' => 'integer']);
        try {
            $n = $svc->notificar($expediente, array_map('intval', $d['partes']), array_map('intval', $d['documentos']), $r->user());
        } catch (FlujoException $x) {
            return back()->with('error', $x->getMessage());
        }

        return back()->with('ok', count($n).' notificación(es) creada(s). Las que son por correo, Casilla Única o DocDigital se envían solas; las de entrega física quedan pendientes hasta registrar el acta.');
    }
}
