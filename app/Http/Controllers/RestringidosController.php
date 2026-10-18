<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Http\Request;

/** Restricciones de acceso a expedientes, documentos y archivos, y bitácora de quién los vio o intentó verlos. */
class RestringidosController extends Controller
{
    public function __construct(private readonly AccesoRestringidoService $svc, private readonly AuditoriaService $audit) {}

    private function hecho(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok);
    }

    /** Resuelve el elemento (archivo o documento del expediente) indicado en la petición, si lo hay. */
    private function elemento(Request $r, Expediente $e): array
    {
        $a = $r->filled('archivo_id') ? Archivo::query()->where('expediente_id', $e->id)->findOrFail((int) $r->input('archivo_id')) : null;
        $d = $r->filled('documento_id') ? Documento::query()->where('expediente_id', $e->id)->findOrFail((int) $r->input('documento_id')) : null;

        return [$a, $d];
    }

    public function restringir(Request $r, Expediente $expediente)
    {
        $d = $r->validate(['motivo' => 'required|string|max:600', 'archivo_id' => 'nullable|integer', 'documento_id' => 'nullable|integer']);
        [$a, $doc] = $this->elemento($r, $expediente);

        return $this->hecho(fn () => $this->svc->restringir($r->user(), $expediente, $d['motivo'], $a, $doc), 'Restricción aplicada: quienes no estén autorizados dejan de ver el contenido.');
    }

    public function liberar(Request $r, Expediente $expediente)
    {
        $r->validate(['archivo_id' => 'nullable|integer', 'documento_id' => 'nullable|integer']);
        [$a, $doc] = $this->elemento($r, $expediente);

        return $this->hecho(fn () => $this->svc->liberar($r->user(), $expediente, $a, $doc), 'Restricción levantada.');
    }

    public function autorizar(Request $r, Expediente $expediente)
    {
        $d = $r->validate(['usuario_id' => 'required|integer', 'motivo' => 'required|string|max:600', 'vence_en' => 'nullable|date_format:Y-m-d', 'archivo_id' => 'nullable|integer', 'documento_id' => 'nullable|integer']);
        [$a, $doc] = $this->elemento($r, $expediente);

        return $this->hecho(fn () => $this->svc->autorizar($r->user(), $expediente, (int) $d['usuario_id'], $d['motivo'], $d['vence_en'] ?? null, $a, $doc), 'Autorización registrada.');
    }

    public function revocar(Request $r, AccesoRestringido $autorizacion)
    {
        return $this->hecho(fn () => $this->svc->revocar($r->user(), $autorizacion), 'Autorización revocada.');
    }

    // ---------------------------------------------------------------- bitácora de accesos (administración y auditoría)
    public function accesos(Request $r)
    {
        $f = $r->validate(['resultado' => 'nullable|in:permitido,denegado', 'persona' => 'nullable|string|max:80', 'expediente' => 'nullable|string|max:40', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d']);
        $q = AuditLog::query()->whereIn('accion', ['restringido.acceso', 'restringido.intento'])->orderByDesc('id');
        if (! empty($f['resultado'])) {
            $q->where('accion', $f['resultado'] === 'permitido' ? 'restringido.acceso' : 'restringido.intento');
        }
        if (! empty($f['desde'])) {
            $q->where('ts', '>=', $f['desde'].' 00:00:00');
        }
        if (! empty($f['hasta'])) {
            $q->where('ts', '<=', $f['hasta'].' 23:59:59.999999');
        }
        if (! empty($f['persona'])) {
            $like = '%'.addcslashes($f['persona'], '%_\\').'%';
            $runs = Usuario::query()->where(fn ($w) => $w->where('nombres', 'like', $like)->orWhere('apellidos', 'like', $like)->orWhere('run', 'like', $like))->pluck('run');
            $q->whereIn('actor_run', $runs);
        }
        if (! empty($f['expediente'])) {
            $ids = Expediente::query()->where('codigo', 'like', '%'.addcslashes($f['expediente'], '%_\\').'%')->pluck('id');
            $q->where(function ($w) use ($ids) {
                foreach ($ids as $id) {
                    $w->orWhere('detalle', 'like', '%"expediente_id":'.(int) $id.'}%')->orWhere('detalle', 'like', '%"expediente_id":'.(int) $id.',%');
                }
                if ($ids->isEmpty()) {
                    $w->whereRaw('1 = 0');
                }
            });
        }
        $pag = $q->paginate(25)->withQueryString();
        $personas = Usuario::query()->whereIn('run', $pag->pluck('actor_run')->filter()->unique())->get()->keyBy('run');
        $expedientes = Expediente::query()->whereIn('id', $pag->map(fn ($x) => json_decode((string) $x->detalle, true)['expediente_id'] ?? null)->filter()->unique())->pluck('codigo', 'id');
        $filas = $pag->getCollection()->map(function ($x) use ($personas, $expedientes) {
            $d = json_decode((string) $x->detalle, true) ?: [];
            $p = $personas->get($x->actor_run);

            return ['ts' => $x->ts, 'persona' => $p ? $p->nombreCompleto() : 'Persona no identificada', 'cargo' => $p?->cargo, 'run' => $x->actor_run ? Run::enmascarar($x->actor_run) : '—', 'rol' => $x->actor_rol,
                'resultado' => $x->accion === 'restringido.acceso' ? 'permitido' : 'denegado', 'accion' => $d['accion'] ?? '', 'entidad' => $x->entidad, 'entidad_id' => $x->entidad_id, 'expediente' => $expedientes[$d['expediente_id'] ?? 0] ?? '—'];
        });
        $hace30 = now()->subDays(30)->format('Y-m-d H:i:s');
        $kpis = [
            ['icono' => 'check-circle', 'valor' => AuditLog::query()->where('accion', 'restringido.acceso')->where('ts', '>=', $hace30)->count(), 'etiqueta' => 'Accesos permitidos (30 días)', 'nivel' => 'ok'],
            ['icono' => 'alert', 'valor' => AuditLog::query()->where('accion', 'restringido.intento')->where('ts', '>=', $hace30)->count(), 'etiqueta' => 'Intentos denegados (30 días)', 'nivel' => AuditLog::query()->where('accion', 'restringido.intento')->where('ts', '>=', $hace30)->exists() ? 'aviso' : 'ok'],
            ['icono' => 'shield', 'valor' => Expediente::query()->where('restringido', true)->count(), 'etiqueta' => 'Expedientes restringidos', 'nivel' => 'info'],
            ['icono' => 'users', 'valor' => AccesoRestringido::query()->vigentes()->count(), 'etiqueta' => 'Autorizaciones vigentes', 'nivel' => 'info'],
        ];
        $restringidos = Expediente::query()->where('restringido', true)->orWhereHas('archivos', fn ($a) => $a->where('restringido', true))->orWhereHas('documentos', fn ($d) => $d->where('restringido', true))->orderBy('codigo')->limit(100)->get(['id', 'codigo', 'restringido', 'restringido_motivo', 'restringido_por', 'restringido_en']);
        $auth = AccesoRestringido::query()->vigentes()->with('usuario:id,nombres,apellidos,cargo')->whereIn('expediente_id', $restringidos->pluck('id'))->get()->groupBy('expediente_id');
        $this->audit->registrar('restringido.auditar_ver', null, null, ['filtros' => array_keys(array_filter($f))], true);

        return view('app.restringidos.accesos', ['f' => $f, 'pag' => $pag, 'filas' => $filas, 'kpis' => $kpis, 'restringidos' => $restringidos, 'auth' => $auth,
            'marcadores' => Usuario::query()->whereIn('id', $restringidos->pluck('restringido_por')->filter())->get(['id', 'nombres', 'apellidos'])->keyBy('id')]);
    }
}
