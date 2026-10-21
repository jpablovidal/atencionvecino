<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\DocumentoPaso;
use App\Models\Expediente;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\AuditoriaService;
use App\Services\DashboardService;
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
            $kpis[] = ['icono' => 'pen', 'valor' => $pasos->count(), 'etiqueta' => 'Visaciones y firmas pendientes', 'nivel' => $pasos->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.firmas')];
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

        return view('app.bandeja', ['entrantes' => $entrantes, 'expedientes' => $expedientes, 'pasos' => $pasos, 'porAsignar' => $porAsignar, 'kpis' => $kpis, 'u' => $u, 'panel' => app(DashboardService::class)->para($u)]);
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
        app(AccesoRestringidoService::class)->exigirArchivo($r->user(), $archivo, 'descargar_archivo');
        $this->audit->registrar('archivo.descarga', 'archivo', $archivo->id, [], true);

        return descarga($vault->get($archivo->ruta, $archivo->sha256), $archivo->nombre_original, $archivo->mime);
    }

    /** Listado final de documentos vigentes (todos los que completaron su flujo) que el usuario puede ver. */
    /** Columnas por las que se puede ordenar el listado de vigentes (lista cerrada). */
    private const ORDEN_VIGENTES = ['numero' => 'documentos.numero', 'fecha' => 'documentos.fecha_firma', 'tipo' => 'tipos_documento.nombre', 'direccion' => 'unidades.nombre', 'materia' => 'documentos.titulo',
        'tipo_materia' => 'documentos.tipo_materia', 'macrotema' => 'documentos.macrotema', 'expediente' => 'expedientes.codigo'];

    /** Todos los documentos vigentes (decretos, oficios y demás), con filtros y orden; por defecto el año actual. El contenido sigue sujeto a los permisos de cada documento. */
    public function vigentes(Request $r)
    {
        $this->permiso('vigentes.ver');
        $u = $r->user();
        abort_unless($u->puedeVerContenido(), 403);
        $f = $r->validate([
            'q' => 'nullable|string|max:100', 'anio' => ['nullable', 'string', 'max:5', 'regex:/^(\d{4}|todos)$/'], 'tipo' => 'nullable|string|max:30', 'unidad' => 'nullable|integer', 'tipo_materia' => 'nullable|string|max:120', 'macrotema' => 'nullable|string|max:120',
            'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d', 'orden' => 'nullable|in:'.implode(',', array_keys(self::ORDEN_VIGENTES)), 'dir' => 'nullable|in:asc,desc', 'por_pagina' => 'nullable|in:25,50,100',
        ]);
        $anio = ($f['anio'] ?? null) ?: (string) now('America/Santiago')->year; // por defecto, el año actual
        $orden = $f['orden'] ?? 'fecha';
        $dir = $f['dir'] ?? 'desc';
        $tipos = TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'prefijo']);
        $esDecreto = fn ($t) => stripos($t->nombre.' '.$t->prefijo, 'decreto') !== false;
        $esOficio = fn ($t) => stripos($t->nombre.' '.$t->prefijo, 'oficio') !== false;

        $q = Documento::query()->select('documentos.*')->with(['tipo', 'expediente.unidad'])
            ->join('expedientes', 'expedientes.id', '=', 'documentos.expediente_id')->leftJoin('unidades', 'unidades.id', '=', 'expedientes.unidad_id')
            ->join('tipos_documento', 'tipos_documento.id', '=', 'documentos.tipo_documento_id')->where('documentos.estado', 'vigente');
        if ($anio !== 'todos') {
            $q->where(fn ($w) => $w->where('documentos.anio', (int) $anio)->orWhere(fn ($x) => $x->whereNull('documentos.anio')->whereYear('documentos.fecha_firma', (int) $anio)));
        }
        $tipo = $f['tipo'] ?? '';
        if ($tipo === 'decretos') {
            $q->whereIn('documentos.tipo_documento_id', $tipos->filter($esDecreto)->pluck('id'));
        } elseif ($tipo === 'oficios') {
            $q->whereIn('documentos.tipo_documento_id', $tipos->filter($esOficio)->pluck('id'));
        } elseif (ctype_digit($tipo) && $tipo !== '') {
            $q->where('documentos.tipo_documento_id', (int) $tipo);
        }
        if (! empty($f['unidad'])) {
            $q->where('expedientes.unidad_id', (int) $f['unidad']);
        }
        foreach (['tipo_materia', 'macrotema'] as $c) {
            if (! empty($f[$c])) {
                $q->where('documentos.'.$c, $f[$c]);
            }
        }
        if (! empty($f['desde'])) {
            $q->where('documentos.fecha_firma', '>=', $f['desde'].' 00:00:00');
        }
        if (! empty($f['hasta'])) {
            $q->where('documentos.fecha_firma', '<=', $f['hasta'].' 23:59:59');
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('documentos.titulo', 'like', $like)->orWhere('documentos.resumen', 'like', $like)->orWhere('documentos.palabras_clave', 'like', $like)
                ->orWhere('expedientes.codigo', 'like', $like)->orWhere('documentos.folio', 'like', $like)->orWhere('documentos.tipo_materia', 'like', $like)->orWhere('documentos.macrotema', 'like', $like)
                ->when(ctype_digit(trim($f['q'])), fn ($x) => $x->orWhere('documentos.numero', (int) trim($f['q']))));
        }
        $q->orderBy(self::ORDEN_VIGENTES[$orden], $dir)->orderByDesc('documentos.id');
        $docs = $q->paginate((int) ($f['por_pagina'] ?? 25))->withQueryString();
        $acceso = app(AccesoRestringidoService::class);
        $ocultos = $docs->getCollection()->filter(fn ($d) => ! $acceso->puedeVerDocumento($u, $d))->pluck('id')->all();
        $abrir = $docs->getCollection()->filter(fn ($d) => ! in_array($d->id, $ocultos, true) && $u->can('ver', $d))->pluck('id')->all();
        $anios = Documento::query()->where('estado', 'vigente')->whereNotNull('anio')->distinct()->orderByDesc('anio')->pluck('anio')->map(fn ($a) => (string) $a)->all();
        if (! in_array((string) now('America/Santiago')->year, $anios, true)) {
            array_unshift($anios, (string) now('America/Santiago')->year);
        }
        $vals = fn (string $c) => Documento::query()->where('estado', 'vigente')->whereNotNull($c)->where($c, '!=', '')->distinct()->orderBy($c)->pluck($c);

        return view('app.vigentes', [
            'docs' => $docs, 'ocultos' => $ocultos, 'abrir' => $abrir, 'f' => $f + ['anio' => $anio, 'tipo' => $tipo, 'orden' => $orden, 'dir' => $dir], 'anios' => $anios, 'tipos' => $tipos, 'decretos' => $tipos->filter($esDecreto), 'oficios' => $tipos->filter($esOficio),
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']), 'tiposMateria' => $vals('tipo_materia'), 'macrotemas' => $vals('macrotema'),
        ]);
    }

    /** Panel de Alcaldía: firmas pendientes, pagos y solicitudes de toda la municipalidad. */
    public function alcaldia(Request $r)
    {
        $this->audit->registrar('alcaldia.panel', null, null, [], false);

        return view('app.alcaldia', ['panel' => app(DashboardService::class)->para($r->user())]);
    }
}
