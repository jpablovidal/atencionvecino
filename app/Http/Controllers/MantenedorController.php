<?php

namespace App\Http\Controllers;

use App\Models\DelegacionFirma;
use App\Models\Institucion;
use App\Models\PiseeServicio;
use App\Models\ProcesoCodificador;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Mantenedores genéricos (solo admin). Sin borrado físico: se desactiva. */
class MantenedorController extends Controller
{
    public const CPAT = '/^[A-Z]{2}-[A-Z]{3}\d{5}-\d{5}$/'; // p. ej. PA-UNI00002-00001 (patrón A VALIDAR)

    public function __construct(private readonly AuditoriaService $audit) {}

    private function def(string $entidad): array
    {
        $defs = [
            'unidades' => ['permiso' => 'mantenedores.gestionar', 'modelo' => Unidad::class, 'titulo' => 'Unidades', 'cols' => ['codigo', 'nombre'], 'campos' => [
                'codigo' => ['Código', 'text', ['required', 'string', 'max:30']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:150']],
            ], 'unico' => ['codigo']],
            'tipos' => ['permiso' => 'mantenedores.gestionar', 'modelo' => TipoDocumento::class, 'titulo' => 'Tipos de documento', 'cols' => ['codigo', 'nombre', 'prefijo'], 'campos' => [
                'codigo' => ['Código', 'text', ['required', 'string', 'max:30']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:120']],
                'prefijo' => ['Prefijo de numeración', 'text', ['required', 'string', 'max:20']],
                'firma_alcalde' => ['Primera firma: alcalde o delegado vigente', 'check', ['nullable', 'boolean']],
                'firmas_agil' => ['Firmas en flujo ágil', 'number', ['required', 'integer', 'between:1,3']],
                'firmas_clasico' => ['Firmas en flujo clásico', 'number', ['required', 'integer', 'between:1,3']],
            ], 'unico' => ['codigo']],
            'instituciones' => ['permiso' => 'codificador.gestionar', 'modelo' => Institucion::class, 'titulo' => 'Codificador: instituciones', 'cols' => ['codigo', 'nombre', 'tipo', 'origen'], 'campos' => [
                'codigo' => ['Código (Codificador)', 'text', ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\\-]*$/']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:190']],
                'tipo' => ['Tipo', 'text', ['nullable', 'string', 'max:60']],
                'codigo_padre' => ['Código de la institución padre', 'text', ['nullable', 'string', 'max:40']],
                'docdigital_entidad_id' => ['Id de la entidad en DocDigital (para enviar documentos)', 'text', ['nullable', 'integer', 'min:1']],
            ], 'unico' => ['codigo']],
            'procesos' => ['permiso' => 'codificador.gestionar', 'modelo' => ProcesoCodificador::class, 'titulo' => 'Codificador: procesos', 'cols' => ['codigo', 'nombre', 'institucion_codigo', 'origen'], 'campos' => [
                'codigo' => ['Código (Codificador)', 'text', ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\\-]*$/']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:190']],
                'institucion_codigo' => ['Código de la institución', 'text', ['nullable', 'string', 'max:40']],
            ], 'unico' => ['codigo']],
            'pisee' => ['permiso' => 'codificador.gestionar', 'modelo' => PiseeServicio::class, 'titulo' => 'Servicios PISEE (lista blanca)', 'cols' => ['codigo', 'nombre', 'ruta', 'metodo'], 'campos' => [
                'codigo' => ['Código interno', 'text', ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\\-]*$/']],
                'nombre' => ['Nombre del servicio', 'text', ['required', 'string', 'max:190']],
                'institucion_codigo' => ['Institución proveedora (código)', 'text', ['nullable', 'string', 'max:40']],
                'ruta' => ['Ruta en el nodo PISEE (ej. /servicio/v1/consulta)', 'text', ['required', 'string', 'max:200', 'regex:#^/[A-Za-z0-9._~\\-]+(/[A-Za-z0-9._~\\-{}]+)*$#']],
                'metodo' => ['Método', 'select:GET,POST', ['required', Rule::in(['GET', 'POST'])]],
                'param_run' => ['Parámetro que lleva el RUN (vacío = no envía RUN)', 'text', ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\\-]+$/']],
                'descripcion' => ['Descripción / base legal', 'textarea', ['nullable', 'string', 'max:2000']],
            ], 'unico' => ['codigo']],
        ];
        abort_unless(isset($defs[$entidad]), 404);
        $this->permiso($defs[$entidad]['permiso']);

        return $defs[$entidad];
    }

    public function index(string $entidad)
    {
        $def = $this->def($entidad);

        return view('app.mantenedor.index', ['entidad' => $entidad, 'def' => $def, 'filas' => $def['modelo']::query()->orderBy('id')->get()]);
    }

    public function crear(string $entidad)
    {
        return view('app.mantenedor.form', $this->datosForm($entidad, null));
    }

    public function editar(string $entidad, int $id)
    {
        $def = $this->def($entidad);

        return view('app.mantenedor.form', $this->datosForm($entidad, $def['modelo']::query()->findOrFail($id)));
    }

    private function datosForm(string $entidad, $fila): array
    {
        return [
            'entidad' => $entidad, 'def' => $this->def($entidad), 'fila' => $fila,
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(), 'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(),
            'procesos' => ProcesoCodificador::query()->where('activo', true)->orderBy('nombre')->get(),
            'sel' => [], 'anexosTxt' => '',
        ];
    }

    private function reglas(array $def, ?int $id): array
    {
        $r = [];
        foreach ($def['campos'] as $k => [, , $reglas]) {
            $r[$k] = $reglas;
        }
        foreach ($def['unico'] as $k) {
            $r[$k][] = Rule::unique((new $def['modelo'])->getTable(), $k)->ignore($id);
        }
        if (isset($r['unidades'])) {
            $r['unidades.*'] = ['integer', 'exists:unidades,id'];
        }

        return $r;
    }

    public function guardar(Request $r, string $entidad)
    {
        return $this->persistir($r, $entidad, null);
    }

    public function actualizar(Request $r, string $entidad, int $id)
    {
        return $this->persistir($r, $entidad, $id);
    }

    private function persistir(Request $r, string $entidad, ?int $id)
    {
        $def = $this->def($entidad);
        $d = $r->validate($this->reglas($def, $id));
        $d = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $d);
        if ($entidad === 'tipos') {
            $d['firma_alcalde'] = (bool) ($d['firma_alcalde'] ?? false);
        }
        DB::transaction(function () use ($def, $entidad, $id, $d) {
            $m = $id ? $def['modelo']::query()->findOrFail($id) : new $def['modelo'];
            $m->fill($d)->save();
            $this->audit->registrar('mantenedor.'.($id ? 'editar' : 'crear'), $entidad, $m->id);
        });

        return redirect()->route('app.admin.index', $entidad)->with('ok', 'Guardado.');
    }

    public function alternar(string $entidad, int $id)
    {
        $m = $this->def($entidad)['modelo']::query()->findOrFail($id);
        $m->activo = ! $m->activo; // sin borrado físico
        $m->save();
        $this->audit->registrar('mantenedor.activo', $entidad, $id, ['activo' => $m->activo]);

        return back()->with('ok', 'Estado actualizado.');
    }

    // ---- Delegaciones de firma: solo el alcalde las crea
    public function delegaciones()
    {
        return view('app.delegaciones', [
            'filas' => DelegacionFirma::query()->with(['tipo', 'delegado'])->orderByDesc('id')->get(),
            'tipos' => TipoDocumento::query()->where('activo', true)->where('firma_alcalde', true)->get(),
            'usuarios' => Usuario::query()->where('activo', true)->where('puede_firmar', true)->operadores()->where('rol', '!=', 'alcalde')->orderBy('apellidos')->get(),
        ]);
    }

    public function crearDelegacion(Request $r)
    {
        $d = $r->validate([
            'tipo_documento_id' => 'required|integer|exists:tipos_documento,id', 'delegado_id' => 'required|integer|exists:usuarios,id',
            'decreto_referencia' => 'required|string|min:3|max:120', 'vigente_desde' => 'required|date', 'vigente_hasta' => 'required|date|after_or_equal:vigente_desde',
        ]);
        $del = Usuario::query()->where('id', $d['delegado_id'])->where('activo', true)->where('puede_firmar', true)->firstOrFail();
        $f = DelegacionFirma::query()->create($d + ['creado_por' => $r->user()->id, 'activo' => true, 'delegado_id' => $del->id]);
        $this->audit->registrar('delegacion.crear', 'delegacion', $f->id, ['tipo' => $d['tipo_documento_id'], 'hasta' => $d['vigente_hasta']]);

        return back()->with('ok', 'Delegación registrada.');
    }

    public function revocarDelegacion(Request $r, DelegacionFirma $delegacion)
    {
        $delegacion->forceFill(['activo' => false])->save();
        $this->audit->registrar('delegacion.revocar', 'delegacion', $delegacion->id);

        return back()->with('ok', 'Delegación revocada.');
    }
}
