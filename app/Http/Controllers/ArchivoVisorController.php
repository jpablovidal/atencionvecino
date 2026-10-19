<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\ArchivoVersion;
use App\Models\Documento;
use App\Models\Expediente;
use App\Services\AccesoRestringidoService;
use App\Services\ArchivoVersionService;
use App\Services\AuditoriaService;
use App\Services\DocumentoHistorialService;
use App\Services\FileVault;
use Illuminate\Http\Request;

/** Visor de archivos del expediente (modal y pestaña nueva), versiones de archivos e historial de versiones de documentos. */
class ArchivoVisorController extends Controller
{
    /** Formatos que el navegador puede mostrar sin riesgo (el resto solo se descarga). */
    private const INLINE = ['application/pdf', 'image/jpeg', 'image/png', 'text/plain', 'text/csv'];

    public function __construct(private readonly AuditoriaService $audit, private readonly ArchivoVersionService $versiones, private readonly AccesoRestringidoService $restringidos) {}

    private function cargar(Request $r, Expediente $expediente, Archivo $archivo): void
    {
        $this->authorize('ver', $expediente);
        abort_unless($archivo->expediente_id === $expediente->id, 404);
    }

    /** Muestra el archivo (vigente o una versión) dentro de la propia página: se enmarca solo desde el mismo origen. */
    public function ver(Request $r, Expediente $expediente, Archivo $archivo, FileVault $vault, ?int $numero = null)
    {
        $this->cargar($r, $expediente, $archivo);
        $this->restringidos->exigirArchivo($r->user(), $archivo, 'ver_archivo');
        $v = $numero !== null ? ArchivoVersion::query()->where('archivo_id', $archivo->id)->where('numero', $numero)->firstOrFail() : null;
        $ruta = $v?->ruta ?? $archivo->ruta;
        $sha = $v?->sha256 ?? $archivo->sha256;
        $mime = $v?->mime ?? $archivo->mime;
        $nombre = $v?->nombre_original ?? $archivo->nombre_original;
        $this->audit->registrar('archivo.ver', 'archivo', $archivo->id, ['version' => $v?->numero ?? $archivo->version], true);
        $inline = in_array($mime, self::INLINE, true);
        if (str_starts_with($mime, 'text/')) {
            $mime .= '; charset=UTF-8';
        }
        $resp = descarga($vault->get($ruta, $sha), $nombre, $mime, $inline);
        $inline && $resp->headers->set('X-SGD-Embebible', '1'); // SecurityHeaders lo traduce a «solo mismo origen» para este archivo

        return $resp;
    }

    public function descargarVersion(Request $r, Expediente $expediente, Archivo $archivo, int $numero, FileVault $vault)
    {
        $this->cargar($r, $expediente, $archivo);
        $this->restringidos->exigirArchivo($r->user(), $archivo, 'descargar_archivo');
        $v = ArchivoVersion::query()->where('archivo_id', $archivo->id)->where('numero', $numero)->firstOrFail();
        $this->audit->registrar('archivo.descarga', 'archivo', $archivo->id, ['version' => $v->numero], true);

        return descarga($vault->get($v->ruta, $v->sha256), $v->nombre_original, $v->mime);
    }

    public function historial(Request $r, Expediente $expediente, Archivo $archivo)
    {
        $this->cargar($r, $expediente, $archivo);
        $this->restringidos->exigirArchivo($r->user(), $archivo, 'ver_historial_archivo');

        return view('app.archivo_versiones', ['e' => $expediente, 'a' => $archivo, 'historial' => $this->versiones->historial($archivo), 'puedeOperar' => $r->user()->can('operar', $expediente),
            'versionable' => in_array($archivo->ambito, ArchivoVersionService::VERSIONABLES, true) && ! $archivo->documento_id && $expediente->estado === 'abierto', 'inline' => self::INLINE]);
    }

    public function nuevaVersion(Request $r, Expediente $expediente, Archivo $archivo)
    {
        $this->permiso('expedientes.operar');
        $this->authorize('operar', $expediente);
        $this->cargar($r, $expediente, $archivo);
        abort_unless($expediente->estado === 'abierto', 422);
        $d = $r->validate(['archivo' => 'required|file', 'motivo' => 'required|string|max:500']);
        try {
            $v = $this->versiones->nuevaVersion($archivo, $r->file('archivo'), $r->user(), $d['motivo']);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.expediente.archivo.versiones', [$expediente, $archivo])->with('ok', 'Se creó la versión '.$v->numero.'. La anterior se conserva en el historial.');
    }

    public function documentoHistorial(Request $r, Expediente $expediente, Documento $documento, DocumentoHistorialService $svc)
    {
        $this->authorize('ver', $expediente);
        abort_unless($documento->expediente_id === $expediente->id, 404);
        $this->restringidos->exigirDocumento($r->user(), $documento, 'ver_historial_documento');

        return view('app.documento_versiones', ['e' => $expediente, 'd' => $documento, 'historial' => $svc->historial($documento)]);
    }
}
