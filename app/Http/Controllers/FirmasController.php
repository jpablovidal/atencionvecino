<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\FirmaEstilo;
use App\Models\FirmaPosicion;
use App\Models\TipoDocumento;
use App\Services\Firma\FirmaConfigService;
use App\Services\Firma\FirmaRenderer;
use App\Services\PdfService;
use Illuminate\Http\Request;

class FirmasController extends Controller
{
    public function __construct(private readonly FirmaConfigService $svc) {}

    public function index(Request $r, FirmaRenderer $renderer)
    {
        $tipoId = $r->integer('tipo') ?: null;

        return view('app.admin.firmas', [
            'estilos' => FirmaEstilo::query()->orderByDesc('defecto')->orderBy('nombre')->get(),
            'posiciones' => FirmaPosicion::query()->with(['tipo', 'estilo'])->orderBy('tipo_documento_id')->orderBy('orden_firma')->get(),
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(),
            'editE' => $r->integer('editar_estilo') ? FirmaEstilo::query()->find($r->integer('editar_estilo')) : null,
            'editP' => $r->integer('editar_posicion') ? FirmaPosicion::query()->find($r->integer('editar_posicion')) : null,
            'tipoPrev' => $tipoId, 'muestra' => $renderer->muestra($tipoId, 2),
        ]);
    }

    private function datosEstilo(Request $r): array
    {
        return $r->validate([
            'nombre' => 'required|string|max:80', 'texto_encabezado' => 'required|string|max:80', 'color_borde' => 'required|string|max:7', 'color_fondo' => 'required|string|max:7', 'color_texto' => 'required|string|max:7',
            'tamano_fuente' => 'required|integer', 'ancho_mm' => 'required|integer', 'alto_mm' => 'required|integer', 'borde_estilo' => 'required|in:solid,dashed,dotted,none', 'alineacion_texto' => 'required|in:left,center',
        ]) + $r->only(['mostrar_nombre', 'mostrar_cargo', 'mostrar_run', 'mostrar_fecha', 'mostrar_codigo']);
    }

    public function guardarEstilo(Request $r, ?FirmaEstilo $estilo = null)
    {
        try {
            $this->svc->guardarEstilo($this->datosEstilo($r), $estilo, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.firmas')->with('ok', 'Estilo guardado.');
    }

    public function estiloDefecto(FirmaEstilo $estilo)
    {
        return $this->aplicar(fn () => $this->svc->definirEstiloDefecto($estilo), 'Estilo por defecto actualizado.');
    }

    public function estiloAlternar(FirmaEstilo $estilo)
    {
        return $this->aplicar(fn () => $this->svc->alternarEstilo($estilo), 'Estado actualizado.');
    }

    public function guardarPosicion(Request $r, ?FirmaPosicion $posicion = null)
    {
        $d = $r->validate([
            'nombre' => 'required|string|max:80', 'tipo_documento_id' => 'nullable|integer|exists:tipos_documento,id', 'orden_firma' => 'required|integer|between:1,3', 'pagina' => 'required|in:ultima,primera',
            'alineacion' => 'required|in:izquierda,centro,derecha', 'margen_superior_mm' => 'required|integer|between:0,60', 'x_mm' => 'nullable|integer', 'y_mm' => 'nullable|integer', 'estilo_id' => 'nullable|integer',
        ]) + ['activo' => $r->boolean('activo')];
        try {
            $this->svc->guardarPosicion($d, $posicion, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.firmas')->with('ok', 'Posición guardada.');
    }

    public function posicionAlternar(FirmaPosicion $posicion)
    {
        return $this->aplicar(fn () => $this->svc->alternarPosicion($posicion), 'Estado actualizado.');
    }

    private function aplicar(callable $f, string $ok)
    {
        try {
            $f();
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok);
    }

    public function prueba(Request $r, PdfService $pdf)
    {
        $tipo = $r->integer('tipo') ?: null;

        return descarga($pdf->pruebaFirmas($tipo), 'prueba-firmas.pdf', 'application/pdf', true);
    }
}
