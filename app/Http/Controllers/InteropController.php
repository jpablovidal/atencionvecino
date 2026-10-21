<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\Comentario;
use App\Models\ComunicacionOficial;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\PiseeServicio;
use App\Models\ProcesoCodificador;
use App\Services\AccesoRestringidoService;
use App\Services\Admin\CodificacionService;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use App\Services\Interop\CodificadorService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\PiseeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InteropController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    // ---- PISEE
    public function pisee(Request $r, Expediente $expediente, PiseeService $svc)
    {
        $this->permiso('pisee.consultar');
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
        $this->permiso('comunicaciones.despachar');
        $this->authorize('operar', $expediente);
        $d = $r->validate(['documento_id' => 'required|integer', 'institucion_codigo' => 'required|string|max:40', 'asunto' => 'required|string|min:3|max:190', 'anexos' => 'nullable|array|max:50', 'anexos.*' => 'integer']);
        $doc = Documento::query()->where('expediente_id', $expediente->id)->findOrFail($d['documento_id']);
        try {
            $svc->crearSaliente($expediente, $doc, $d['institucion_codigo'], $d['asunto'], $r->user(), array_map('intval', $d['anexos'] ?? []));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $svc->modoApi() ? 'Comunicación encolada para envío por DocDigital.' : 'Comunicación creada y PENDIENTE: envíela por DocDigital y registre el folio como evidencia.');
    }

    public function evidencia(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        $this->permiso('comunicaciones.despachar');
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

    public function devolver(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        $this->permiso('comunicaciones.acusar');
        if ($comunicacion->direccion !== 'entrante' || $comunicacion->expediente_id === null) {
            abort(404);
        }
        $this->authorize('operar', Expediente::query()->findOrFail($comunicacion->expediente_id));
        $d = $r->validate(['motivo' => 'required|string|min:3|max:255']);
        try {
            $svc->devolver($comunicacion, $r->user(), $d['motivo']);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Documento devuelto a la entidad de origen por DocDigital.');
    }

    /** Estado de gestión de una comunicación recibida (en gestión, respondida o archivada). Exige el acuse de recibo registrado; queda como comentario del expediente y en la auditoría. */
    public function gestion(Request $r, ComunicacionOficial $comunicacion)
    {
        $this->permiso('comunicaciones.acusar');
        if ($comunicacion->direccion !== 'entrante' || $comunicacion->expediente_id === null) {
            abort(404);
        }
        $e = Expediente::query()->findOrFail($comunicacion->expediente_id);
        $this->authorize('operar', $e);
        $d = $r->validate(['gestion' => ['required', Rule::in(['en_gestion', 'respondida', 'archivada'])], 'nota' => 'nullable|string|max:500']);
        if ($comunicacion->acuse_estado !== 'registrado') {
            return back()->with('error', 'Primero debe acusar recibo de la comunicación (o devolverla) antes de cambiar su estado de gestión.');
        }
        if ($comunicacion->gestion === $d['gestion']) {
            return back()->with('error', 'La comunicación ya está en ese estado.');
        }
        $et = ['en_gestion' => 'en gestión', 'respondida' => 'respondida', 'archivada' => 'archivada'][$d['gestion']];
        $nota = trim((string) ($d['nota'] ?? ''));
        $comunicacion->forceFill(['gestion' => $d['gestion'], 'gestion_en' => now(), 'gestion_por_id' => $r->user()->id])->save();
        Comentario::query()->create(['expediente_id' => $e->id, 'usuario_id' => $r->user()->id, 'tipo' => 'comentario', 'texto' => 'Comunicación recibida por DocDigital marcada como '.$et.($nota !== '' ? ': '.$nota : '')]);
        $this->audit->registrar('docdigital.gestion', 'comunicacion', $comunicacion->id, ['gestion' => $d['gestion']]);

        return back()->with('ok', 'La comunicación quedó '.$et.'.');
    }

    public function acuse(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        $this->permiso('comunicaciones.acusar');
        if ($comunicacion->direccion !== 'entrante' || $comunicacion->expediente_id === null) {
            abort(404);
        }
        $this->authorize('operar', Expediente::query()->findOrFail($comunicacion->expediente_id));
        try {
            if ($svc->modoApi()) {
                $svc->acusarRecibo($comunicacion, $r->user());
            } else {
                $d = $r->validate(['evidencia' => 'required|string|max:200']);
                $svc->acusarManual($comunicacion, $r->user(), $d['evidencia']);
            }
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Acuse de recibo registrado.');
    }

    public function reintentar(Request $r, ComunicacionOficial $comunicacion, ComunicacionService $svc)
    {
        $this->permiso('comunicaciones.despachar');
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
        $this->permiso('comunicaciones.ver');
        $u = $r->user();
        abort_unless($u->puedeVerContenido(), 403);
        $q = ComunicacionOficial::query()->where('direccion', 'entrante')->orderByDesc('id');
        if (! $u->veTodo()) {
            $q->where('unidad_id', $u->unidad_id);
        }

        return view('app.comunicaciones', ['filas' => $q->limit(200)->get()]);
    }

    public function archivo(Request $r, ComunicacionOficial $comunicacion, FileVault $vault)
    {
        $u = $r->user();
        abort_unless($comunicacion->direccion === 'entrante' && $comunicacion->archivo_id && ($u->veTodo() || ($u->operaExpedientes() && $u->unidad_id === $comunicacion->unidad_id)), 404);
        $a = Archivo::query()->findOrFail($comunicacion->archivo_id);
        app(AccesoRestringidoService::class)->exigirArchivo($u, $a, 'descargar_comunicacion');
        $this->audit->registrar('docdigital.descarga', 'comunicacion', $comunicacion->id, [], true);

        return descarga($vault->get($a->ruta, $a->sha256), $a->nombre_original, 'application/pdf');
    }

    // ---- Codificador (solo admin)
    public function codificador()
    {
        $this->permiso('codificador.gestionar');

        return view('app.codificador', [
            'inst' => Institucion::query()->count(), 'proc' => ProcesoCodificador::query()->count(),
            'modo' => config('sgd.codificador.mode'), 'miCodigo' => CodificacionService::municipio()['codigo'], 'resultado' => session('resultado'),
        ]);
    }

    public function importar(Request $r, CodificadorService $svc)
    {
        $this->permiso('codificador.gestionar');
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
        $this->permiso('codificador.gestionar');
        try {
            $res = $svc->sincronizar();
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Sincronización completada: '.json_encode(array_map(fn ($x) => [$x['creados'], $x['actualizados']], $res)));
    }
}
