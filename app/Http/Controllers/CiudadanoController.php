<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\FileVault;
use App\Services\MovimientoService;
use App\Services\SolicitudService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CiudadanoController extends Controller
{
    /** Largo máximo del detalle de la solicitud (caracteres). */
    public const MAX_DETALLE = 2000;

    /** Política de Privacidad vigente a la que se refiere el consentimiento. */
    public const CONSENTIMIENTO_VERSION = 'Decreto Alcaldicio 2590 de 31-08-2026';

    public function __construct(private readonly AuditoriaService $audit) {}

    private function ciudadano(Request $r): array
    {
        return $r->session()->get('ciudadano');
    }

    /** Anti-IDOR: siempre folio Y RUN de la sesión. */
    private function propia(Request $r, string $folio): Solicitud
    {
        return Solicitud::query()->where('folio', $folio)->where('run', $this->ciudadano($r)['run'])->firstOrFail();
    }

    public function index(Request $r)
    {
        $c = $this->ciudadano($r);

        return view('ciudadano.index', [
            'c' => $c,
            'solicitudes' => $sol = Solicitud::query()->with('tramite', 'expediente:id,codigo')->where('run', $c['run'])->orderByDesc('id')->get(),
            'resumen' => ['devuelta' => $sol->where('estado', 'devuelta')->count(), 'finalizada' => $sol->whereIn('estado', ['finalizada', 'cerrada'])->count(), 'curso' => $sol->whereNotIn('estado', ['devuelta', 'finalizada', 'cerrada'])->count()],
            'tramites' => Tramite::query()->publicados()->with('propietaria')->orderBy('nombre')->get()->groupBy(fn ($t) => $t->propietaria?->nombre ?? 'Municipalidad'),
        ]);
    }

    public function nueva(Request $r, Tramite $tramite)
    {
        abort_unless($tramite->estaPublicado(), 404);

        return view('ciudadano.nueva', ['c' => $this->ciudadano($r), 'tramite' => $tramite->load('anexos', 'unidades')]);
    }

    public function store(Request $r, SolicitudService $svc)
    {
        $c = $this->ciudadano($r);
        $r->merge(['detalle' => str_replace("\r\n", "\n", (string) $r->input('detalle'))]); // el navegador cuenta un salto de línea como 1 carácter; el envío lo manda como 2
        $d = $r->validate([
            'tramite_id' => 'required|integer|exists:tramites,id',
            'correo' => 'required|email:rfc|max:190',
            'detalle' => 'required|string|min:10|max:'.self::MAX_DETALLE,
            'canal_notificacion' => 'required|in:correo,presencial,casilla_unica',
            'consentimiento' => 'required|in:si,no',
            'en_nombre_de' => 'nullable|in:1',
            'tercero_run' => 'required_if:en_nombre_de,1|nullable|string|max:14', 'tercero_nombre' => 'required_if:en_nombre_de,1|nullable|string|max:190', 'tercero_correo' => 'nullable|string|max:190',
            'tercero_relacion' => ['required_if:en_nombre_de,1', 'nullable', Rule::in(array_keys(Solicitud::RELACIONES))], 'tercero_acreditacion' => 'required_if:en_nombre_de,1|nullable|string|max:500', 'tercero_declara' => 'required_if:en_nombre_de,1|nullable|in:1',
        ], ['detalle.max' => 'El detalle no puede superar los '.self::MAX_DETALLE.' caracteres.', 'consentimiento.required' => 'Indique si consiente o no el tratamiento de sus datos personales.']);
        $tramite = Tramite::query()->with('anexos')->findOrFail($d['tramite_id']);
        $requeridos = [];
        foreach ($tramite->anexos as $a) {
            if ($r->hasFile('anexo_'.$a->id)) {
                $requeridos[$a->id] = $r->file('anexo_'.$a->id);
            }
        }
        $extras = array_values(array_filter((array) $r->file('extras', [])));
        try {
            // RUN, nombres y apellidos vienen SOLO de la sesión (ClaveÚnica); cualquier valor enviado se ignora.
            $s = $svc->ingresar($c, $tramite, $d['correo'], $d['detalle'], $d['canal_notificacion'], $requeridos, $extras, $d['consentimiento'] === 'si', self::CONSENTIMIENTO_VERSION,
                ! empty($d['en_nombre_de']) ? ['run' => $d['tercero_run'], 'nombre' => $d['tercero_nombre'], 'correo' => $d['tercero_correo'] ?? null, 'relacion' => $d['tercero_relacion'], 'acreditacion' => $d['tercero_acreditacion'], 'declara' => ! empty($d['tercero_declara'])] : null);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($s->en_nombre_de) {
            $this->audit->registrar('solicitud.en_nombre_de', 'solicitud', $s->folio, ['relacion' => $s->relacion], false, ['run' => $c['run'], 'rol' => 'ciudadano']);
        }
        if (! $s->consentimiento_datos) {
            $this->audit->registrar('solicitud.sin_consentimiento', 'solicitud', $s->folio, ['version' => self::CONSENTIMIENTO_VERSION], false, ['run' => $c['run'], 'rol' => 'ciudadano']);
        }

        return redirect()->route('ciudadano.solicitud', $s->folio)->with('ok', 'Solicitud ingresada. Su folio es '.$s->folio.'.');
    }

    public function solicitud(Request $r, string $folio)
    {
        $s = $this->propia($r, $folio)->load('tramite', 'devoluciones', 'expediente.unidad');
        $env = Notificacion::query()->with('documento.tipo', 'archivo')->where('solicitud_id', $s->id)->where('estado', 'enviada')->get();
        $mov = app(MovimientoService::class);

        return view('ciudadano.solicitud', ['s' => $s, 'docs' => $env->pluck('documento')->filter()->unique('id'), 'archivos' => $env->pluck('archivo')->filter()->unique('id'), 'pendiente' => $s->devolucionPendiente(),
            'linea' => $mov->linea($s), 'direccion' => $mov->direccion($s)]);
    }

    /** El titular puede cambiar su respuesta sobre el tratamiento de sus datos (autorizar o revocar) en cualquier momento; no tiene efecto retroactivo (Decreto 12, art. 15). */
    public function consentimiento(Request $r, string $folio)
    {
        $s = $this->propia($r, $folio);
        $d = $r->validate(['consentimiento' => 'required|in:si,no']);
        $nuevo = $d['consentimiento'] === 'si';
        if ($s->consentimiento_datos === $nuevo) {
            return back()->with('error', 'Su respuesta ya es esa.');
        }
        $antes = $s->consentimiento_datos === null ? 'sin dato' : ($s->consentimiento_datos ? 'si' : 'no');
        $s->forceFill(['consentimiento_datos' => $nuevo, 'consentimiento_en' => now(), 'consentimiento_version' => self::CONSENTIMIENTO_VERSION])->save();
        $this->audit->registrar($nuevo ? 'solicitud.consentimiento_otorgado' : 'solicitud.consentimiento_revocado', 'solicitud', $s->folio, ['antes' => $antes, 'version' => self::CONSENTIMIENTO_VERSION], false, ['run' => $s->run, 'rol' => 'ciudadano']);

        return back()->with('ok', $nuevo ? 'Registramos que autoriza el tratamiento de sus datos personales para esta solicitud.' : 'Registramos que no autoriza el tratamiento de sus datos. Esto no afecta lo ya realizado y su solicitud sigue su curso.');
    }

    public function responder(Request $r, string $folio, SolicitudService $svc)
    {
        $s = $this->propia($r, $folio);
        $d = $r->validate(['respuesta' => 'required|string|min:5|max:4000']);
        try {
            $svc->responder($s, $d['respuesta'], array_values(array_filter((array) $r->file('archivos', []))));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Respuesta enviada.');
    }

    public function comprobante(Request $r, string $folio, FileVault $vault)
    {
        $s = $this->propia($r, $folio);
        $a = Archivo::query()->where('solicitud_id', $s->id)->where('ambito', 'comprobante')->firstOrFail();
        $this->audit->registrar('solicitud.comprobante', 'solicitud', $s->folio);

        return descarga($vault->get($a->ruta, $a->sha256), $a->nombre_original, 'application/pdf');
    }

    /** Solo archivos efectivamente notificados (evidencia registrada) a ESTE ciudadano. */
    public function archivoRespuesta(Request $r, string $folio, Archivo $archivo, FileVault $vault)
    {
        $s = $this->propia($r, $folio);
        $ok = Notificacion::query()->where('solicitud_id', $s->id)->where('archivo_id', $archivo->id)->where('estado', 'enviada')->exists();
        abort_unless($ok, 404);
        $this->audit->registrar('archivo.descarga_ciudadano', 'archivo', $archivo->id, [], $archivo->sensible, ['run' => $s->run, 'rol' => 'ciudadano']);

        return descarga($vault->get($archivo->ruta, $archivo->sha256), $archivo->nombre_original, $archivo->mime);
    }

    /** Solo documentos efectivamente notificados (evidencia registrada) a ESTE ciudadano. */
    public function documento(Request $r, string $folio, Documento $documento, DocumentoService $svc)
    {
        $s = $this->propia($r, $folio);
        $ok = Notificacion::query()->where('solicitud_id', $s->id)->where('documento_id', $documento->id)->where('estado', 'enviada')->exists();
        abort_unless($ok && $documento->estado === 'vigente', 404);
        $this->audit->registrar('documento.descarga_ciudadano', 'documento', $documento->id, [], $documento->sensible);

        return descarga($svc->leerPdf($documento), 'documento-'.$documento->numero.'-'.$documento->anio.'.pdf', 'application/pdf');
    }
}
