<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\ComunicacionOficial;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\PiseeServicio;
use App\Models\ProcesoCodificador;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use App\Services\Interop\CodificadorService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\PiseeService;
use Illuminate\Http\Request;

class InteropController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    // ---- PISEE
    public function pisee(Request $r, Expediente $expediente, PiseeService $svc)
    {
        $this->authorize('operar', $expediente);
        $d = $r->validate(['servicio_id' => 'required|integer|exists:pisee_servicios,id', 'finalidad' => 'required|string|min:10|max:255']);
        try {
            $c = $svc->consultar($expediente->loadMissing('solicitud'), PiseeServicio::query()->findOrFail($d['servicio_id']), $r->user(), $d['finalidad']);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with($c->estado === 'ok' ? 'ok' : 'error', $c->estado === 'ok' ? 'Consulta PISEE registrada; el resultado quedó cifrado en el expediente.' : (string) $c->error);
    }

    // ---- DocDigital: saliente
    public function comunicar(Request $r, Expediente $expediente, ComunicacionService $svc)
    {
        $this->authorize('operar', $expediente);
        $d = $r->validate(['documento_id' => 'required|integer', 'institucion_codigo' => 'required|string|max:40', 'asunto' => 'required|string|min:3|max:190']);
        $doc = Documento::query()->where('expediente_id', $expediente->id)->findOrFail($d['documento_id']);
        try {
            $svc->crearSaliente($expediente, $doc, $d['institucion_codigo'], $d['asunto'], $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $svc->modoApi() ? 'Comunicación encolada para envío por DocDigital.' : 'Comunicación creada y PENDIENTE: envíela por DocDigital y registre el folio como evidencia.');
    }

    public function evidencia(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        if ($comunicacion->expediente_id === null) {
            abort(404);
        }
        $this->authorize('operar', Expediente::query()->findOrFail($comunicacion->expediente_id));
        $d = $r->validate(['folio' => 'required|string|max:120']);
        try {
            $svc->registrarEvidencia($comunicacion, $d['folio']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Evidencia registrada.');
    }

    public function reintentar(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        if ($comunicacion->expediente_id === null || $comunicacion->estado !== 'fallida' || ! $svc->modoApi()) {
            abort(404);
        }
        $this->authorize('operar', Expediente::query()->findOrFail($comunicacion->expediente_id));
        EnviarComunicacionDocDigital::dispatch($comunicacion->id);

        return back()->with('ok', 'Reintento encolado.');
    }

    // ---- DocDigital: bandeja de entrantes de mi unidad
    public function bandeja(Request $r)
    {
        $u = $r->user();
        abort_unless($u->operaExpedientes() || $u->rol === 'auditor', 403);
        $q = ComunicacionOficial::query()->where('direccion', 'entrante')->orderByDesc('id');
        if ($u->rol !== 'auditor') {
            $q->where('unidad_id', $u->unidad_id);
        }

        return view('app.comunicaciones', ['filas' => $q->limit(200)->get()]);
    }

    public function archivo(Request $r, ComunicacionOficial $comunicacion, FileVault $vault)
    {
        $u = $r->user();
        abort_unless($comunicacion->direccion === 'entrante' && $comunicacion->archivo_id && ($u->rol === 'auditor' || ($u->operaExpedientes() && $u->unidad_id === $comunicacion->unidad_id)), 404);
        $a = Archivo::query()->findOrFail($comunicacion->archivo_id);
        $this->audit->registrar('docdigital.descarga', 'comunicacion', $comunicacion->id, [], true);

        return descarga($vault->get($a->ruta, $a->sha256), $a->nombre_original, 'application/pdf');
    }

    // ---- Codificador (solo admin)
    public function codificador()
    {
        return view('app.codificador', [
            'inst' => Institucion::query()->count(), 'proc' => ProcesoCodificador::query()->count(),
            'modo' => config('sgd.codificador.mode'), 'miCodigo' => config('sgd.institucion_codigo'), 'resultado' => session('resultado'),
        ]);
    }

    public function importar(Request $r, CodificadorService $svc)
    {
        $d = $r->validate(['entidad' => 'required|in:instituciones,procesos', 'archivo' => 'required|file|max:5120']);
        try {
            $res = $svc->importarCsv($d['entidad'], (string) file_get_contents($r->file('archivo')->getRealPath()));
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('resultado', $res)->with('ok', 'Carga completada.');
    }

    public function sincronizar(CodificadorService $svc)
    {
        try {
            $res = $svc->sincronizar();
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Sincronización completada: '.json_encode(array_map(fn ($x) => [$x['creados'], $x['actualizados']], $res)));
    }
}
