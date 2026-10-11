<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\TipoDocumento;
use App\Services\FolioService;
use Illuminate\Http\Request;

class FoliosController extends Controller
{
    public function __construct(private readonly FolioService $svc) {}

    public function index()
    {
        $tipos = TipoDocumento::query()->orderBy('nombre')->get();
        $anio = (int) now('America/Santiago')->year;
        $filas = $tipos->map(function (TipoDocumento $t) use ($anio) {
            $cont = collect($this->svc->contadores($t));
            $clave = $t->folio_reinicio_anual ? $anio : 0;
            $ultimo = (int) ($cont->firstWhere('anio', $clave)['ultimo'] ?? max(0, $t->folio_inicio - 1));

            return ['tipo' => $t, 'proximo' => $this->svc->formatear($t, $ultimo + 1, $anio), 'ultimo' => $ultimo];
        });

        return view('app.admin.folios', ['filas' => $filas, 'anio' => $anio]);
    }

    public function editar(TipoDocumento $tipo)
    {
        $anio = (int) now('America/Santiago')->year;

        return view('app.admin.folio_form', [
            't' => $tipo, 'contadores' => $this->svc->contadores($tipo), 'anio' => $anio,
            'ejemplo' => [$this->svc->formatear($tipo, $tipo->folio_inicio, $anio), $this->svc->formatear($tipo, $tipo->folio_inicio + 1, $anio), $this->svc->formatear($tipo, 123, $anio + 1)],
        ]);
    }

    public function actualizar(Request $r, TipoDocumento $tipo)
    {
        $d = $r->validate(['folio_formato' => 'required|string|max:80', 'folio_digitos' => 'required|integer|between:0,8', 'folio_inicio' => 'required|integer|min:1|max:99999999', 'folio_reinicio_anual' => 'nullable|boolean']);
        try {
            $this->svc->guardarConfiguracion($tipo, ['folio_reinicio_anual' => (bool) ($d['folio_reinicio_anual'] ?? false)] + $d, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.folios')->with('ok', 'Configuración de folios guardada. Aplica a los documentos que se firmen desde ahora.');
    }

    public function ajustar(Request $r, TipoDocumento $tipo)
    {
        $d = $r->validate(['anio' => 'required|integer|min:0|max:2200', 'ultimo' => 'required|integer|min:0|max:99999999', 'motivo' => 'required|string|min:5|max:255']);
        try {
            $this->svc->ajustarContador($tipo, (int) $d['anio'], (int) $d['ultimo'], $d['motivo'], $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Contador ajustado.');
    }
}
