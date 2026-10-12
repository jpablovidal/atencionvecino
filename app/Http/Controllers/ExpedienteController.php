<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Comentario;
use App\Models\ComunicacionOficial;
use App\Models\Derivacion;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\Notificacion;
use App\Models\PiseeConsulta;
use App\Models\PiseeServicio;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\NotificacionService;
use App\Services\SolicitudService;
use App\Support\UploadValidator;
use Illuminate\Http\Request;

class ExpedienteController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function show(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->authorize('ver', $expediente);
        $expediente->load(['solicitud.tramite', 'solicitud.devoluciones', 'documentos.tipo', 'documentos.pasos.usuario', 'archivos', 'comentarios.usuario', 'derivaciones.de', 'derivaciones.a', 'notificaciones.documento', 'responsable', 'unidad']);
        $this->audit->registrar('expediente.ver', 'expediente', $expediente->id, [], true);

        return view('app.expediente', [
            'e' => $expediente, 'indice' => $svc->indice($expediente),
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(),
            'plantillas' => Plantilla::query()->where('activo', true)->get(),
            'usuarios' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->get(),
            'puedeOperar' => $r->user()->can('operar', $expediente),
            'instituciones' => Institucion::query()->where('activo', true)->orderBy('nombre')->get(['codigo', 'nombre']),
            'servicios' => PiseeServicio::query()->where('activo', true)->orderBy('nombre')->get(),
            'consultas' => PiseeConsulta::query()->with(['servicio', 'usuario'])->where('expediente_id', $expediente->id)->orderByDesc('id')->get(),
            'comunicaciones' => ComunicacionOficial::query()->with('documento')->where('expediente_id', $expediente->id)->orderByDesc('id')->get(),
            'piseeOn' => in_array(config('sgd.pisee.mode'), ['mock', 'api'], true),
            'piseeMock' => config('sgd.pisee.mode') === 'mock',
            'docModo' => config('sgd.docdigital.mode'),
            'entrante' => $expediente->origen === 'docdigital' ? ComunicacionOficial::query()->find($expediente->comunicacion_id) : null,
            'origenCodigo' => $expediente->metadatos['relaciones']['institucion_origen_codigo'] ?? null,
        ]);
    }

    private function operar(Expediente $e): void
    {
        $this->authorize('operar', $e);
    }

    public function metadatos(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->permiso('expedientes.operar');
        $this->operar($expediente);
        $d = $r->validate(['resumen' => 'nullable|string|max:1000', 'serie' => 'nullable|string|max:120', 'nivel_acceso' => 'required|in:publico,reservado']);
        $svc->actualizarMetadatos($expediente, $d);

        return back()->with('ok', 'Metadatos actualizados.');
    }

    public function comentar(Request $r, Expediente $expediente)
    {
        $this->permiso('expedientes.operar');
        $this->operar($expediente);
        $d = $r->validate(['texto' => 'required|string|max:2000', 'tipo' => 'required|in:comentario,observacion']);
        Comentario::query()->create(['expediente_id' => $expediente->id, 'usuario_id' => $r->user()->id] + $d);
        $this->audit->registrar('expediente.comentar', 'expediente', $expediente->id);

        return back()->with('ok', 'Comentario agregado.');
    }

    public function devolver(Request $r, Expediente $expediente, SolicitudService $svc)
    {
        $this->permiso('expedientes.devolver');
        $this->operar($expediente);
        if (! $expediente->solicitud) {
            return back()->with('error', 'Este expediente no proviene de una solicitud ciudadana.');
        }
        $d = $r->validate(['observacion' => 'required|string|min:5|max:2000', 'plazo_dias' => 'required|integer|between:1,90']);
        try {
            $svc->devolver($expediente->solicitud, $r->user(), $d['observacion'], (int) $d['plazo_dias']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Solicitud devuelta al ciudadano.');
    }

    public function derivar(Request $r, Expediente $expediente)
    {
        $this->permiso('expedientes.derivar');
        $this->operar($expediente);
        $d = $r->validate(['a_usuario_id' => 'required|integer|exists:usuarios,id', 'motivo' => 'required|string|min:3|max:500']);
        $dest = Usuario::query()->where('id', $d['a_usuario_id'])->where('activo', true)->firstOrFail();
        abort_unless($dest->operaExpedientes(), 422);
        Derivacion::query()->create(['expediente_id' => $expediente->id, 'de_usuario_id' => $r->user()->id, 'a_usuario_id' => $dest->id, 'motivo' => $d['motivo']]);
        $this->audit->registrar('expediente.derivar', 'expediente', $expediente->id, ['a' => $dest->id]);

        return back()->with('ok', 'Expediente derivado a '.$dest->nombreCompleto().'.');
    }

    public function subir(Request $r, Expediente $expediente, FileVault $vault)
    {
        $this->permiso('expedientes.operar');
        $this->operar($expediente);
        abort_unless($expediente->estado === 'abierto', 422);
        $d = $r->validate([
            'archivo' => 'required|file', 'titulo' => 'required|string|max:190', 'resumen' => 'nullable|string|max:1000',
            'palabras_clave' => 'nullable|string|max:255', 'nivel_acceso' => 'required|in:publico,reservado', 'sensible' => 'nullable|boolean',
        ]);
        try {
            $tipo = UploadValidator::validar($r->file('archivo'));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $f = $vault->put((string) file_get_contents($r->file('archivo')->getRealPath()));
        Archivo::query()->create([
            'expediente_id' => $expediente->id, 'solicitud_id' => $expediente->solicitud_id, 'ambito' => 'expediente',
            'nombre_original' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($r->file('archivo')->getClientOriginalName())) ?: 'archivo', 0, 190),
            'mime' => $tipo['mime'], 'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => $d['titulo'], 'resumen' => $d['resumen'] ?? null,
            'palabras_clave' => $d['palabras_clave'] ?? null, 'nivel_acceso' => $d['nivel_acceso'], 'sensible' => (bool) ($d['sensible'] ?? false), 'subido_por' => $r->user()->run,
        ]);
        $this->audit->registrar('archivo.subir', 'expediente', $expediente->id, ['sha256' => $f['sha256']]);

        return back()->with('ok', 'Archivo cargado.');
    }

    public function archivo(Request $r, Expediente $expediente, Archivo $archivo, FileVault $vault)
    {
        $this->authorize('ver', $expediente);
        abort_unless($archivo->expediente_id === $expediente->id, 404);
        $this->audit->registrar('archivo.descarga', 'archivo', $archivo->id, [], true);

        return descarga($vault->get($archivo->ruta, $archivo->sha256), $archivo->nombre_original, $archivo->mime);
    }

    public function asignarme(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->operar($expediente);
        try {
            $svc->asignarResponsable($expediente, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Expediente asignado a usted.');
    }

    public function cerrar(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->permiso('expedientes.cerrar');
        $this->operar($expediente);
        try {
            $svc->cerrar($expediente);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Expediente cerrado.');
    }

    public function reabrir(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->permiso('expedientes.cerrar');
        $this->operar($expediente);
        $d = $r->validate(['motivo' => 'required|string|min:5|max:500']);
        try {
            $svc->reabrir($expediente, $d['motivo']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Expediente reabierto.');
    }

    public function exportar(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->permiso('expedientes.exportar');
        $this->authorize('ver', $expediente);
        $json = json_encode($svc->exportar($expediente), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return descarga((string) $json, 'expediente-'.$expediente->codigo.'.json', 'application/json');
    }

    public function notificar(Request $r, Expediente $expediente, NotificacionService $svc)
    {
        $this->permiso('notificaciones.gestionar');
        $this->operar($expediente);
        $d = $r->validate(['documentos' => 'required|array|min:1|max:50', 'documentos.*' => 'integer', 'canal' => 'required|in:casilla_unica,correo,presencial']);
        try {
            $svc->crear($expediente, array_map('intval', $d['documentos']), $d['canal'], $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Notificaciones creadas. Quedan "pendientes" hasta que exista evidencia de envío.');
    }

    public function evidencia(Request $r, Notificacion $notificacion, NotificacionService $svc)
    {
        $this->permiso('notificaciones.gestionar');
        $notificacion->load('expediente');
        $this->operar($notificacion->expediente);
        $d = $r->validate(['tipo' => 'required|in:id_casilla_unica,acta_presencial', 'valor' => 'required|string|max:250']);
        try {
            $svc->registrarEvidencia($notificacion, $d['tipo'], $d['valor']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Evidencia registrada.');
    }
}
