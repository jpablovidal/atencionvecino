<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Services\Admin\ArchivoConfigService;
use App\Services\Admin\CodificacionService;
use Illuminate\Http\Request;

/** Administrador del sistema: archivos (pesos y formatos) y codificación normativa (municipio, patrones, normas técnicas). */
class AdminConfigController extends Controller
{
    public function __construct(private readonly ArchivoConfigService $archivos, private readonly CodificacionService $cod) {}

    public function archivosForm()
    {
        $ini = ['upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size')];

        return view('app.admin.archivos', ['cfg' => ArchivoConfigService::actual(), 'formatos' => ArchivoConfigService::FORMATOS, 'ambitos' => ArchivoConfigService::AMBITOS,
            'ini' => $ini, 'limitePhpMb' => round(ArchivoConfigService::limitePhp() / 1048576, 1), 'techo' => ArchivoConfigService::TECHO_MB]);
    }

    public function archivosGuardar(Request $r)
    {
        $d = $r->validate(['formatos' => 'nullable|array', 'formatos.*' => 'nullable|boolean', 'max_mb' => 'required|array', 'max_mb.*' => 'required|integer', 'max_archivos' => 'required|integer']);
        try {
            $this->archivos->guardar($r->user(), $d);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Configuración de archivos guardada.');
    }

    public function codificacion(Request $r)
    {
        $p = CodificacionService::patrones();
        $q = trim((string) $r->query('q', ''));
        $inst = Institucion::query()->where('activo', true)->when($q !== '', function ($w) use ($q) {
            $t = '%'.addcslashes($q, '%_\\').'%';
            $w->where(fn ($x) => $x->where('nombre', 'like', $t)->orWhere('codigo', 'like', $t));
        })->orderBy('nombre')->limit(50)->get();
        $ejExp = Expediente::query()->latest('id')->first();
        $ejDoc = Documento::query()->whereNotNull('numero')->latest('id')->first();

        return view('app.admin.codificacion', ['municipio' => CodificacionService::municipio(), 'patrones' => $p, 'inst' => $inst, 'q' => $q, 'total' => Institucion::query()->count(),
            'normas' => CodificacionService::NORMAS, 'matriz' => CodificacionService::matrizNormas(), 'defecto' => CodificacionService::DEFECTO,
            'tokensExp' => CodificacionService::TOKENS_EXPEDIENTE, 'tokensDoc' => CodificacionService::TOKENS_DOCUMENTO,
            'ejExp' => $ejExp ? $this->cod->identificadorExpediente($ejExp, $p['expediente']) : null, 'ejDoc' => $ejDoc ? $this->cod->identificadorDocumento($ejDoc, $p['documento']) : null]);
    }

    public function elegirMunicipio(Request $r)
    {
        $d = $r->validate(['codigo' => 'required|string|max:30', 'manual' => 'nullable|boolean']);
        try {
            $this->cod->elegirMunicipio($r->user(), $d['codigo'], ! empty($d['manual']));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Municipio configurado.');
    }

    public function guardarPatrones(Request $r)
    {
        $d = $r->validate(['expediente' => 'required|string|max:100', 'documento' => 'required|string|max:100']);
        try {
            $this->cod->guardarPatrones($r->user(), $d);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Patrones de codificación guardados. Rigen para los documentos y expedientes que se identifiquen desde ahora.');
    }
}
