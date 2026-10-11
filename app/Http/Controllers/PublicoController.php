<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\SolicitudService;
use Illuminate\Http\Request;

class PublicoController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function inicio()
    {
        return view('publico.inicio');
    }

    /** Listado público: solo vigentes marcados públicos y no sensibles. */
    public function listado(Request $request)
    {
        $f = $request->validate(['tipo' => 'nullable|integer', 'q' => 'nullable|string|max:100', 'anio' => 'nullable|integer|between:2000,2100']);
        $q = Documento::query()->with('tipo')->where('estado', 'vigente')->where('nivel_acceso', 'publico')->where('sensible', false);
        if (! empty($f['tipo'])) {
            $q->where('tipo_documento_id', $f['tipo']);
        }
        if (! empty($f['anio'])) {
            $q->where('anio', $f['anio']);
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('titulo', 'like', $like)->orWhere('palabras_clave', 'like', $like));
        }
        $this->audit->registrar('publico.listado', null, null, [], false, ['run' => null, 'rol' => 'publico']);

        return view('publico.listado', ['docs' => $q->orderByDesc('fecha_firma')->paginate(15)->withQueryString(), 'tipos' => TipoDocumento::query()->where('activo', true)->get(), 'f' => $f]);
    }

    public function formValidar()
    {
        return view('publico.validar_form');
    }

    public function buscarValidar(Request $request)
    {
        $d = $request->validate(['codigo' => ['required', 'string', 'max:30', 'regex:/^SDD-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/']]);

        return redirect()->route('validar', strtoupper($d['codigo']));
    }

    public function validar(string $codigo)
    {
        $doc = Documento::query()->with(['tipo', 'pasos.usuario'])->where('codigo_validacion', $codigo)->where('estado', 'vigente')->first();
        $this->audit->registrar('publico.validar', 'documento', $doc?->id, ['encontrado' => (bool) $doc], false, ['run' => null, 'rol' => 'publico']);

        return view('publico.validar', ['doc' => $doc, 'codigo' => $codigo]);
    }

    /** La copia pública se entrega solo si el documento es público y no sensible. */
    public function copia(string $codigo, DocumentoService $svc)
    {
        $doc = Documento::query()->where('codigo_validacion', $codigo)->first();
        abort_unless($doc && $doc->esPublico(), 404);
        $this->audit->registrar('publico.copia', 'documento', $doc->id, [], false, ['run' => null, 'rol' => 'publico']);

        return descarga($svc->leerPdf($doc), 'documento-'.$doc->numero.'-'.$doc->anio.'.pdf', 'application/pdf', true);
    }

    public function validarComprobante(string $folio, string $codigo)
    {
        $s = Solicitud::query()->where('folio', $folio)->first();
        $ok = false;
        if ($s) {
            $hashes = Archivo::query()->where('solicitud_id', $s->id)->where('ambito', 'anexo')->pluck('sha256')->all();
            $ok = hash_equals(SolicitudService::codigoComprobante($s->folio, $hashes), $codigo);
        }
        $this->audit->registrar('publico.validar_comprobante', 'solicitud', $folio, ['ok' => $ok], false, ['run' => null, 'rol' => 'publico']);

        return view('publico.validar_comprobante', ['ok' => $ok, 'folio' => $folio, 's' => $ok ? $s : null]);
    }
}
