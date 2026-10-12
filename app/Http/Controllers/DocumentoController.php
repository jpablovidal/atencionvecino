<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\FileVault;
use App\Services\Firma\FirmaPdfInspector;
use App\Services\LlmAsistente;
use Illuminate\Http\Request;

class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $svc, private readonly AuditoriaService $audit) {}

    public function store(Request $r, Expediente $expediente)
    {
        $this->permiso('documentos.crear');
        $this->authorize('operar', $expediente);
        $d = $r->validate(['tipo_documento_id' => 'required|integer|exists:tipos_documento,id', 'plantilla_id' => 'nullable|integer|exists:plantillas,id', 'titulo' => 'required|string|max:190']);
        $tipo = TipoDocumento::query()->where('activo', true)->findOrFail($d['tipo_documento_id']);
        $pl = ! empty($d['plantilla_id']) ? Plantilla::query()->visiblesPara($r->user())->where('activo', true)->where('tipo_documento_id', $tipo->id)->where('id', $d['plantilla_id'])->first() : null;
        if (! empty($d['plantilla_id']) && ! $pl) {
            return back()->with('error', 'La plantilla elegida no corresponde al tipo de documento o no está disponible para su unidad.');
        }
        try {
            $doc = $this->svc->crear($expediente, $tipo, $pl, $d['titulo'], $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('app.documento', $doc)->with('ok', 'Documento creado.');
    }

    public function show(Request $r, Documento $documento, FirmaPdfInspector $inspector, FileVault $vault)
    {
        $this->authorize('ver', $documento);
        $documento->load(['tipo', 'pasos.usuario', 'versiones', 'expediente']);
        $this->audit->registrar('documento.ver', 'documento', $documento->id, [], $documento->sensible);
        $u = $r->user();
        $firmaPdf = null;
        if ($documento->estado === 'vigente' && $documento->pdf_firmado_ruta) {
            try {
                $firmaPdf = $inspector->inspeccionar($vault->get($documento->pdf_firmado_ruta, (string) $documento->pdf_firmado_sha256));
            } catch (\Throwable) {
                $firmaPdf = ['estado' => 'no_verificable', 'firmas' => [], 'observaciones' => ['No se pudo leer el PDF firmado.']];
            }
        }
        $turno = $documento->estado === 'en_flujo' ? $documento->pasos->where('estado', 'pendiente')->sortBy('orden')->first() : null;

        return view('app.documento', [
            'd' => $documento, 'firmaPdf' => $firmaPdf, 'turno' => $turno, 'miTurno' => $turno && $turno->usuario_id === $u->id,
            'puedeOperar' => $u->can('operar', $documento),
            'usuarios' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->get(),
            'llm' => (bool) config('sgd.llm.enabled'),
            'completos' => $documento->estado === 'en_flujo' && $documento->pasos->isNotEmpty() && $documento->pasos->every(fn ($p) => $p->estado === 'completado'),
        ]);
    }

    public function guardar(Request $r, Documento $documento)
    {
        $this->permiso('documentos.crear');
        $this->authorize('operar', $documento);
        $d = $r->validate([
            'titulo' => 'required|string|max:190', 'html' => 'required|string|max:200000', 'resumen' => 'nullable|string|max:1000', 'palabras_clave' => 'nullable|string|max:255',
            'nivel_acceso' => 'required|in:publico,reservado', 'sensible' => 'nullable|boolean',
        ]);
        try {
            $this->svc->guardar($documento, $d['titulo'], $d['html'], [
                'resumen' => $d['resumen'] ?? null, 'palabras_clave' => $d['palabras_clave'] ?? null, 'nivel_acceso' => $d['nivel_acceso'], 'sensible' => (bool) ($d['sensible'] ?? false),
            ], $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Documento guardado (nueva versión).');
    }

    public function flujo(Request $r, Documento $documento)
    {
        $this->authorize('operar', $documento);
        $d = $r->validate(['flujo' => 'required|in:agil,clasico', 'visadores' => 'nullable|array|max:5', 'visadores.*' => 'nullable|integer', 'firmantes' => 'required|array|min:1|max:2', 'firmantes.*' => 'nullable|integer']);
        $vis = array_values(array_filter(array_map('intval', $d['visadores'] ?? [])));
        $fir = array_values(array_filter(array_map('intval', $d['firmantes'])));
        try {
            $this->svc->definirFlujo($documento, $d['flujo'], $vis, $fir);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Flujo de visación/firma iniciado.');
    }

    public function visar(Request $r, Documento $documento)
    {
        $d = $r->validate(['accion' => 'required|in:aprobar,rechazar', 'comentario' => 'nullable|string|max:1000']);
        $this->authorize('ver', $documento);
        try {
            $this->svc->visar($documento, $r->user(), $d['accion'] === 'aprobar', $d['comentario'] ?? null);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', $d['accion'] === 'aprobar' ? 'Documento visado.' : 'Documento rechazado; vuelve a borrador.');
    }

    public function firmar(Request $r, Documento $documento)
    {
        $d = $r->validate(['otp' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $this->authorize('ver', $documento);
        try {
            $doc = $this->svc->firmar($documento, $r->user(), $d['otp']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }
        $msg = $doc->estado === 'vigente' ? 'Documento firmado y vigente: '.$doc->numeroFormateado().' · código '.$doc->codigo_validacion : 'Firma registrada.';

        return back()->with('ok', $msg);
    }

    public function finalizar(Request $r, Documento $documento)
    {
        $this->authorize('operar', $documento);
        try {
            $this->svc->finalizar($documento);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No fue posible finalizar ahora. Puede reintentar.');
        }

        return back()->with('ok', 'Documento finalizado.');
    }

    public function pdf(Request $r, Documento $documento)
    {
        $this->authorize('ver', $documento);
        $this->audit->registrar('documento.pdf', 'documento', $documento->id, [], true);

        return descarga($this->svc->leerPdf($documento), 'documento-'.$documento->id.'.pdf', 'application/pdf', true);
    }

    public function llm(Request $r, LlmAsistente $llm)
    {
        $this->permiso('llm.usar');
        $d = $r->validate(['instruccion' => 'required|string|max:1000', 'borrador' => 'nullable|string|max:6000']);
        try {
            $res = $llm->generar($d['instruccion'], strip_tags((string) ($d['borrador'] ?? '')));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json(['error' => 'El asistente no está disponible.'], 503);
        }
        $this->audit->registrar('llm.borrador', null, null, ['chars' => strlen($d['instruccion'])]);

        return response()->json($res);
    }
}
