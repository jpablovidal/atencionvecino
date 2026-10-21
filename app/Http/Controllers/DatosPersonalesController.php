<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Solicitud;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Http\Request;

/** Panel del Oficial de Protección de Datos (DPO): solicitudes cuyo titular no autorizó el tratamiento de sus datos personales. */
class DatosPersonalesController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function index(Request $r)
    {
        $f = $r->validate(['estado' => 'nullable|in:sin_consentimiento,con_consentimiento,sin_dato', 'folio' => 'nullable|string|max:30', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d', 'acc_desde' => 'nullable|date_format:Y-m-d', 'acc_hasta' => 'nullable|date_format:Y-m-d']);
        $estado = $f['estado'] ?? 'sin_consentimiento';
        $q = Solicitud::query()->with(['tramite', 'expediente.unidad'])->orderByDesc('id');
        match ($estado) {
            'con_consentimiento' => $q->where('consentimiento_datos', true),
            'sin_dato' => $q->whereNull('consentimiento_datos'),
            default => $q->where('consentimiento_datos', false),
        };
        if (! empty($f['folio'])) {
            $q->where('folio', 'like', '%'.addcslashes($f['folio'], '%_\\').'%');
        }
        if (! empty($f['desde'])) {
            $q->where('created_at', '>=', $f['desde'].' 00:00:00');
        }
        if (! empty($f['hasta'])) {
            $q->where('created_at', '<=', $f['hasta'].' 23:59:59');
        }
        $kpis = [
            ['etiqueta' => 'No autorizaron el tratamiento', 'valor' => Solicitud::query()->where('consentimiento_datos', false)->count()],
            ['etiqueta' => 'Autorizaron', 'valor' => Solicitud::query()->where('consentimiento_datos', true)->count()],
            ['etiqueta' => 'Sin dato (anteriores a esta función)', 'valor' => Solicitud::query()->whereNull('consentimiento_datos')->count()],
        ];
        $this->audit->registrar('datospersonales.consulta', null, null, ['estado' => $estado]);
        // Registro de accesos a contenido con datos sensibles (Decreto 10, art. 37): quién, cuándo y a qué.
        $accesos = AuditLog::query()->where('sensible', true)->whereIn('accion', ['expediente.ver', 'documento.ver', 'archivo.descarga', 'archivo.ver', 'documento.descarga_ciudadano', 'restringido.acceso', 'restringido.intento'])
            ->when(! empty($f['acc_desde']), fn ($q) => $q->where('ts', '>=', $f['acc_desde'].' 00:00:00'))->when(! empty($f['acc_hasta']), fn ($q) => $q->where('ts', '<=', $f['acc_hasta'].' 23:59:59.999999'))
            ->orderByDesc('id')->limit(100)->get();

        return view('app.datos_personales', ['filas' => $q->paginate(25)->withQueryString(), 'f' => $f + ['estado' => $estado], 'kpis' => $kpis, 'accesos' => $accesos, 'enmascarar' => fn (string $run) => Run::enmascarar($run)]);
    }
}
