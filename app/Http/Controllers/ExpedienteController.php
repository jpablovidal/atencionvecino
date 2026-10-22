<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\Comentario;
use App\Models\ComunicacionOficial;
use App\Models\DecretoPago;
use App\Models\Derivacion;
use App\Models\Expediente;
use App\Models\ExpedienteVinculo;
use App\Models\Institucion;
use App\Models\Notificacion;
use App\Models\PiseeConsulta;
use App\Models\PiseeServicio;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Firma\ArchivoFirmaService;
use App\Services\MovimientoService;
use App\Services\NotificacionService;
use App\Services\SolicitudService;
use App\Services\TransparenciaService;
use App\Support\Plazos;
use App\Support\UploadValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpedienteController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function show(Request $r, Expediente $expediente, ExpedienteService $svc, ArchivoFirmaService $firmas)
    {
        $this->authorize('ver', $expediente);
        $expediente->archivos()->whereNull('firma_estado')->limit(10)->get()->each(fn ($a) => $firmas->reanalizar($a)); // archivos anteriores al reconocimiento de firmas
        $expediente->load(['solicitud.tramite', 'solicitud.devoluciones', 'documentos.tipo', 'documentos.pasos.usuario', 'archivos', 'comentarios.usuario', 'derivaciones.de', 'derivaciones.a', 'notificaciones.documento', 'notificaciones.archivo', 'respuestas.items.documento', 'respuestas.items.archivo', 'respuestas.notificaciones', 'respuestas.instructor', 'responsable', 'unidad']);
        $this->audit->registrar('expediente.ver', 'expediente', $expediente->id, [], true);

        return view('app.expediente', [
            'e' => $expediente, 'indice' => $svc->indice($expediente, $r->user()), 'ocultos' => app(AccesoRestringidoService::class)->ocultos($r->user(), $expediente),
            'restr' => $this->datosRestringidos($r->user(), $expediente),
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(),
            'plantillas' => Plantilla::query()->visiblesPara($r->user())->where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->get(),
            'puedeOperar' => $r->user()->can('operar', $expediente),
            'servicios' => PiseeServicio::query()->where('activo', true)->orderBy('nombre')->get(),
            'consultas' => PiseeConsulta::query()->with(['servicio', 'usuario'])->where('expediente_id', $expediente->id)->orderByDesc('id')->get(),
            'comunicaciones' => ComunicacionOficial::query()->with(['documento', 'anexos'])->where('expediente_id', $expediente->id)->orderByDesc('id')->get(),
            'piseeOn' => in_array(config('sgd.pisee.mode'), ['mock', 'api'], true),
            'piseeMock' => config('sgd.pisee.mode') === 'mock',
            'docModo' => config('sgd.docdigital.mode'),
            'entrante' => $expediente->origen === 'docdigital' ? ComunicacionOficial::query()->with('anexos')->find($expediente->comunicacion_id) : null,
            'origenCodigo' => $expediente->metadatos['relaciones']['institucion_origen_codigo'] ?? null,
            'origenNombre' => ($oc = $expediente->metadatos['relaciones']['institucion_origen_codigo'] ?? null) ? Institucion::query()->where('codigo', $oc)->value('nombre') : null,
            'transp' => $this->datosTransparencia($r->user(), $expediente),
            'vinculos' => $expediente->vinculos()->with('origen:id,codigo,estado', 'microforma:id,codigo,titulo')->get(),
            'referenciadoPor' => ExpedienteVinculo::query()->with('expediente')->where('tipo', 'expediente')->where('origen_expediente_id', $expediente->id)->get()->filter(fn ($vv) => $r->user()->can('ver', $vv->expediente))->values(),
            'decretoPago' => DecretoPago::query()->where('expediente_id', $expediente->id)->where('estado', 'abierto')->first(),
            'copias' => $expediente->archivos->whereNotNull('vinculo_id'),
            'partes' => $expediente->partes()->get(), 'funcionariosPartes' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->get(),
        ]);
    }

    /** Estado de restricción del expediente y autorizaciones (solo para quien puede administrarlas). */
    private function datosRestringidos(Usuario $u, Expediente $e): ?array
    {
        if (! $u->tienePermiso('restringidos.gestionar') || $u->rolBase() === 'admin') {
            return null;
        }
        $svc = app(AccesoRestringidoService::class);
        $autorizado = $svc->autorizado($u, $e);

        return ['gestiona' => $e->estado !== null, 'autorizado' => $autorizado, 'autorizaciones' => $autorizado || ! $e->restringido ? AccesoRestringido::query()->vigentes()->with('usuario:id,nombres,apellidos,cargo')->where('expediente_id', $e->id)->orderBy('id')->get() : collect(),
            'candidatos' => Usuario::query()->where('activo', true)->where('id', '!=', $u->id)->get(['id', 'nombres', 'apellidos', 'cargo', 'rol'])->filter(fn ($x) => $x->operaExpedientes() || $x->rolBase() === 'auditor')->values()];
    }

    /** Ficha de transparencia (plazo, prórroga, requerimientos y derivación) cuando la solicitud es de acceso a la información. */
    private function datosTransparencia(Usuario $u, Expediente $e): ?array
    {
        $s = $e->solicitud;
        if (! $s || ! $s->tramite->es_transparencia || ! $u->tienePermiso('transparencia.ver')) {
            return null;
        }
        $vence = Plazos::deSolicitud($s);
        $svc = app(TransparenciaService::class);

        return ['s' => $s, 'vence' => $vence, 'situacion' => Plazos::situacion($vence), 'dias' => Plazos::diasRestantes($vence), 'habiles' => $s->tramite->plazo_habiles,
            'requerimientos' => $s->requerimientos()->with('unidad:id,nombre')->get(), 'derivaciones' => $s->derivacionesOrgano()->get(), 'puede' => $u->tienePermiso('transparencia.gestionar') && $e->estado === 'abierto',
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']), 'prorrogaDias' => $svc->param('prorroga_dias'), 'reqDias' => $svc->param('requerimiento_dias'),
            'canales' => TransparenciaService::CANALES, 'terminada' => in_array($s->estado, ['finalizada', 'cerrada'], true)];
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
        $d = $r->validate([
            'a_usuario_id' => 'required|integer|exists:usuarios,id', 'motivo' => 'required|string|min:3|max:500',
            'accion' => ['nullable', Rule::in(array_keys(Derivacion::ACCIONES))], 'plazo' => 'nullable|date_format:Y-m-d|after_or_equal:today',
        ]);
        $dest = Usuario::query()->where('id', $d['a_usuario_id'])->where('activo', true)->firstOrFail();
        abort_unless($dest->operaExpedientes(), 422);
        Derivacion::query()->create(['expediente_id' => $expediente->id, 'de_usuario_id' => $r->user()->id, 'a_usuario_id' => $dest->id, 'motivo' => $d['motivo'],
            'accion' => $d['accion'] ?? 'otra', 'plazo' => $d['plazo'] ?? null, 'estado' => 'pendiente']);
        $this->audit->registrar('expediente.derivar', 'expediente', $expediente->id, ['a' => $dest->id, 'accion' => $d['accion'] ?? 'otra', 'plazo' => $d['plazo'] ?? null]);
        if ($expediente->solicitud) {
            app(MovimientoService::class)->registrar($expediente->solicitud, 'derivada', 'Su solicitud fue derivada a '.($dest->unidad?->nombre ?? 'otra dirección municipal').' para continuar su gestión.', $dest->unidad_id);
        }

        return back()->with('ok', 'Expediente derivado a '.$dest->nombreCompleto().': deberá '.mb_strtolower(Derivacion::ACCIONES[$d['accion'] ?? 'otra']).'.');
    }

    /** La persona a quien se derivó (o un superadministrador) informa el avance: en proceso, cumplida o devuelta (no corresponde). Queda como comentario del expediente y en la auditoría. */
    public function estadoDerivacion(Request $r, Expediente $expediente, Derivacion $derivacion)
    {
        abort_unless($derivacion->expediente_id === $expediente->id && $derivacion->estado !== null, 404);
        abort_unless($r->user()->id === $derivacion->a_usuario_id || $r->user()->esSuperadmin(), 403);
        $d = $r->validate(['estado' => ['required', Rule::in(['en_proceso', 'cumplida', 'devuelta'])], 'nota' => 'nullable|string|max:1000']);
        if (! $derivacion->abierta()) {
            return back()->with('error', 'Esta derivación ya está '.mb_strtolower(Derivacion::ESTADOS[$derivacion->estado]).'.');
        }
        if ($d['estado'] === $derivacion->estado) {
            return back()->with('error', 'La derivación ya está en ese estado.');
        }
        if (in_array($d['estado'], ['cumplida', 'devuelta'], true) && mb_strlen(trim((string) ($d['nota'] ?? ''))) < 3) {
            return back()->withInput()->with('error', 'Indique una nota (qué se hizo o por qué no corresponde).');
        }
        $nota = trim((string) ($d['nota'] ?? '')) ?: null;
        $derivacion->forceFill(['estado' => $d['estado'], 'nota' => $nota, 'resuelto_en' => $derivacion->abierta() && $d['estado'] !== 'en_proceso' ? now() : null])->save();
        Comentario::query()->create(['expediente_id' => $expediente->id, 'usuario_id' => $r->user()->id, 'tipo' => 'comentario',
            'texto' => 'Derivación «'.Derivacion::ACCIONES[$derivacion->accion ?? 'otra'].'» → '.Derivacion::ESTADOS[$d['estado']].($nota ? ': '.$nota : '')]);
        $this->audit->registrar('expediente.derivacion_estado', 'expediente', $expediente->id, ['derivacion' => $derivacion->id, 'estado' => $d['estado']]);

        return back()->with('ok', 'Estado de la derivación actualizado: '.mb_strtolower(Derivacion::ESTADOS[$d['estado']]).'.');
    }

    public function subir(Request $r, Expediente $expediente, FileVault $vault, ArchivoFirmaService $firmas)
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
        $bytes = (string) file_get_contents($r->file('archivo')->getRealPath());
        $f = $vault->put($bytes);
        $a = new Archivo([
            'expediente_id' => $expediente->id, 'solicitud_id' => $expediente->solicitud_id, 'ambito' => 'expediente',
            'nombre_original' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($r->file('archivo')->getClientOriginalName())) ?: 'archivo', 0, 190),
            'mime' => $tipo['mime'], 'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => $d['titulo'], 'resumen' => $d['resumen'] ?? null,
            'palabras_clave' => $d['palabras_clave'] ?? null, 'nivel_acceso' => $d['nivel_acceso'], 'sensible' => (bool) ($d['sensible'] ?? false), 'subido_por' => $r->user()->run,
        ]);
        $firmas->analizar($a, $bytes);
        $a->save();
        $this->audit->registrar('archivo.subir', 'expediente', $expediente->id, ['sha256' => $f['sha256']]);

        return back()->with('ok', 'Archivo cargado.');
    }

    public function archivo(Request $r, Expediente $expediente, Archivo $archivo, FileVault $vault)
    {
        $this->authorize('ver', $expediente);
        abort_unless($archivo->expediente_id === $expediente->id, 404);
        app(AccesoRestringidoService::class)->exigirArchivo($r->user(), $archivo, 'descargar_archivo');
        $this->audit->registrar('archivo.descarga', 'archivo', $archivo->id, [], true);

        return descarga($vault->get($archivo->ruta, $archivo->sha256), $archivo->nombre_original, $archivo->mime);
    }

    public function asignarme(Request $r, Expediente $expediente, ExpedienteService $svc)
    {
        $this->permiso('expedientes.operar');
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
        $json = json_encode($svc->exportar($expediente, $r->user()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
