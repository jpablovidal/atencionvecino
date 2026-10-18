<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\DocumentoPaso;
use App\Models\Expediente;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Firma\ArchivoFirmaService;
use App\Services\TramiteService;
use Illuminate\Http\Request;

class FuncionarioController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function bandeja(Request $r)
    {
        $u = $r->user();
        $entrantes = collect();
        $mios = Expediente::query()->with('solicitud.tramite')->orderByDesc('id');
        $pasos = collect();
        $porAsignar = collect();
        if ($u->operaExpedientes()) {
            $entrantes = Solicitud::query()->with('tramite')->where('estado', 'ingresada')->whereNull('expediente_id')->where('run', '!=', $u->run)
                ->whereHas('tramite.unidades', fn ($q) => $q->where('unidades.id', $u->unidad_id))->orderBy('id')->get();
            $porAsignar = Expediente::query()->where('origen', 'docdigital')->whereNull('responsable_id')->where('estado', 'abierto')->where('unidad_id', $u->unidad_id)->orderBy('id')->get();
            $mios->whereDoesntHave('solicitud', fn ($s) => $s->where('run', $u->run))->where(fn ($q) => $q->where('unidad_id', $u->unidad_id)->orWhere('responsable_id', $u->id)
                ->orWhereHas('derivaciones', fn ($d) => $d->where('a_usuario_id', $u->id))
                ->orWhereHas('documentos.pasos', fn ($p) => $p->where('usuario_id', $u->id)));
            $pasos = DocumentoPaso::query()->with('documento.expediente')->where('usuario_id', $u->id)->where('estado', 'pendiente')
                ->whereHas('documento', fn ($d) => $d->where('estado', 'en_flujo'))->get()
                ->filter(fn ($p) => ! DocumentoPaso::query()->where('documento_id', $p->documento_id)->where('estado', 'pendiente')->where('orden', '<', $p->orden)->exists());
        }

        $expedientes = $mios->limit(100)->get();
        $kpis = [];
        if ($u->operaExpedientes()) {
            $kpis[] = ['icono' => 'pen', 'valor' => $pasos->count(), 'etiqueta' => 'Visaciones y firmas pendientes', 'nivel' => $pasos->isNotEmpty() ? 'aviso' : 'ok'];
            $kpis[] = ['icono' => 'inbox', 'valor' => $entrantes->count(), 'etiqueta' => 'Solicitudes por asignar', 'nivel' => $entrantes->isNotEmpty() ? 'info' : 'ok'];
            if ($porAsignar->isNotEmpty() || $u->tienePermiso('comunicaciones.ver')) {
                $kpis[] = ['icono' => 'mail', 'valor' => $porAsignar->count(), 'etiqueta' => 'Comunicaciones por gestionar', 'nivel' => $porAsignar->isNotEmpty() ? 'info' : 'ok'];
            }
            $kpis[] = ['icono' => 'folder', 'valor' => $expedientes->where('estado', 'abierto')->count(), 'etiqueta' => 'Expedientes abiertos', 'nivel' => 'ok'];
        }
        if ($u->tienePermiso('tramites.gestionar')) {
            $t = app(TramiteService::class)->gestionables($u);
            $kpis[] = ['icono' => 'clipboard', 'valor' => (clone $t)->where('estado', 'publicado')->count(), 'etiqueta' => 'Trámites publicados', 'nivel' => 'ok', 'url' => route('app.tramites.index', ['estado' => 'publicado'])];
            $kpis[] = ['icono' => 'file-text', 'valor' => (clone $t)->where('estado', 'borrador')->count(), 'etiqueta' => 'Trámites en borrador', 'nivel' => 'info', 'url' => route('app.tramites.index', ['estado' => 'borrador'])];
        }
        if ($u->rolBase() === 'admin') {
            $kpis[] = ['icono' => 'users', 'valor' => Usuario::query()->where('activo', true)->count(), 'etiqueta' => 'Usuarios activos', 'nivel' => 'ok', 'url' => route('app.admin.index', 'usuarios')];
        }
        if ($u->tienePermiso('seguridad.ver') || $u->tienePermiso('logs.ver')) {
            $kpis[] = ['icono' => 'activity', 'valor' => AuditLog::query()->where('ts', '>=', now()->subDay())->count(), 'etiqueta' => 'Eventos de auditoría (24 h)', 'nivel' => 'ok', 'url' => route('app.logs')];
        }

        return view('app.bandeja', ['entrantes' => $entrantes, 'expedientes' => $expedientes, 'pasos' => $pasos, 'porAsignar' => $porAsignar, 'kpis' => $kpis, 'u' => $u]);
    }

    private function solicitudDeMiUnidad(Request $r, string $folio): Solicitud
    {
        $u = $r->user();
        $s = Solicitud::query()->with('tramite.unidades', 'archivos')->where('folio', $folio)->firstOrFail();
        abort_unless($u->operaExpedientes() && $s->run !== $u->run && $s->tramite->unidades->contains('id', $u->unidad_id), 403); // sin conflicto de interés: no tramita lo propio

        return $s;
    }

    public function solicitud(Request $r, string $folio)
    {
        $s = $this->solicitudDeMiUnidad($r, $folio);
        if ($s->expediente_id) {
            return redirect()->route('app.expediente', $s->expediente_id);
        }
        $s->archivos()->whereNull('firma_estado')->limit(10)->get()->each(fn ($a) => app(ArchivoFirmaService::class)->reanalizar($a));
        $s->unsetRelation('archivos');
        $this->audit->registrar('solicitud.ver', 'solicitud', $s->folio, [], true);

        return view('app.solicitud', ['s' => $s]);
    }

    public function asignar(Request $r, string $folio, ExpedienteService $svc)
    {
        $s = $this->solicitudDeMiUnidad($r, $folio);
        try {
            $e = $svc->crearDesdeSolicitud($s, $r->user());
        } catch (FlujoException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return redirect()->route('app.expediente', $e)->with('ok', 'Solicitud asignada. Expediente '.$e->codigo.' creado.');
    }

    public function archivoSolicitud(Request $r, string $folio, Archivo $archivo, FileVault $vault)
    {
        $s = $this->solicitudDeMiUnidad($r, $folio);
        abort_unless($archivo->solicitud_id === $s->id && in_array($archivo->ambito, ['anexo', 'respuesta', 'comprobante'], true), 404); // no PISEE ni cargas del expediente
        if ($s->expediente_id) {
            $this->authorize('ver', $s->expediente); // una vez abierto el expediente rige su política de acceso, no solo la unidad del trámite
        }
        $this->audit->registrar('archivo.descarga', 'archivo', $archivo->id, [], true);

        return descarga($vault->get($archivo->ruta, $archivo->sha256), $archivo->nombre_original, $archivo->mime);
    }

    /** Listado final de documentos vigentes (todos los que completaron su flujo) que el usuario puede ver. */
    public function vigentes(Request $r)
    {
        $this->permiso('vigentes.ver');
        $u = $r->user();
        abort_unless($u->operaExpedientes() || $u->rolBase() === 'auditor', 403);
        $q = Documento::query()->with(['tipo', 'expediente'])->where('estado', 'vigente');
        if ($u->rolBase() !== 'auditor') {
            $q->whereHas('expediente', fn ($e) => $e->where('unidad_id', $u->unidad_id)->orWhere('responsable_id', $u->id)
                ->orWhereHas('derivaciones', fn ($d) => $d->where('a_usuario_id', $u->id))
                ->orWhereHas('documentos.pasos', fn ($p) => $p->where('usuario_id', $u->id)));
        }

        return view('app.vigentes', ['docs' => $q->orderByDesc('fecha_firma')->paginate(25)]);
    }
}
