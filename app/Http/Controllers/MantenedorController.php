<?php

namespace App\Http\Controllers;

use App\Models\DelegacionFirma;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\TramiteAnexo;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\HtmlSanitizer;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Mantenedores genéricos (solo admin). Sin borrado físico: se desactiva. */
class MantenedorController extends Controller
{
    public const CPAT = '/^[A-Z]{2}-[A-Z]{3}\d{5}-\d{5}$/'; // p. ej. PA-UNI00002-00001 (patrón A VALIDAR)

    public function __construct(private readonly AuditoriaService $audit, private readonly HtmlSanitizer $san) {}

    private function def(string $entidad): array
    {
        $defs = [
            'unidades' => ['modelo' => Unidad::class, 'titulo' => 'Unidades', 'cols' => ['codigo', 'nombre'], 'campos' => [
                'codigo' => ['Código', 'text', ['required', 'string', 'max:30']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:150']],
            ], 'unico' => ['codigo']],
            'usuarios' => ['modelo' => Usuario::class, 'titulo' => 'Usuarios', 'cols' => ['run', 'nombres', 'apellidos', 'rol'], 'campos' => [
                'run' => ['RUN (con guion y DV)', 'text', ['required', 'string', 'max:12']],
                'nombres' => ['Nombres', 'text', ['required', 'string', 'max:120']],
                'apellidos' => ['Apellidos', 'text', ['required', 'string', 'max:120']],
                'correo' => ['Correo institucional', 'email', ['required', 'email', 'max:190']],
                'cargo' => ['Cargo', 'text', ['nullable', 'string', 'max:120']],
                'rol' => ['Rol', 'select:'.implode(',', Usuario::ROLES), ['required', Rule::in(Usuario::ROLES)]],
                'unidad_id' => ['Unidad', 'unidad', ['nullable', 'integer', 'exists:unidades,id']],
                'puede_firmar' => ['Habilitado para firmar', 'check', ['nullable', 'boolean']],
            ], 'unico' => ['run']],
            'tramites' => ['modelo' => Tramite::class, 'titulo' => 'Trámites y servicios', 'cols' => ['codigo_interno', 'nombre', 'codigo_cpat'], 'campos' => [
                'codigo_interno' => ['Código interno', 'text', ['required', 'string', 'max:40']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:190']],
                'descripcion' => ['Descripción', 'textarea', ['nullable', 'string', 'max:2000']],
                'codigo_cpat' => ['Código CPAT (ej. PA-UNI00002-00001)', 'text', ['nullable', 'regex:'.self::CPAT]],
                'plazo_dias' => ['Plazo (días)', 'number', ['required', 'integer', 'between:1,365']],
                'unidades' => ['Unidades responsables', 'unidades', ['required', 'array', 'min:1']],
                'anexos_txt' => ['Anexos requeridos (uno por línea: Nombre|1 obligatorio, Nombre|0 opcional)', 'textarea', ['nullable', 'string', 'max:2000']],
            ], 'unico' => ['codigo_interno']],
            'tipos' => ['modelo' => TipoDocumento::class, 'titulo' => 'Tipos de documento', 'cols' => ['codigo', 'nombre', 'prefijo'], 'campos' => [
                'codigo' => ['Código', 'text', ['required', 'string', 'max:30']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:120']],
                'prefijo' => ['Prefijo de numeración', 'text', ['required', 'string', 'max:10']],
                'firma_alcalde' => ['Primera firma: alcalde o delegado vigente', 'check', ['nullable', 'boolean']],
                'firmas_agil' => ['Firmas en flujo ágil', 'number', ['required', 'integer', 'between:1,3']],
                'firmas_clasico' => ['Firmas en flujo clásico', 'number', ['required', 'integer', 'between:1,3']],
            ], 'unico' => ['codigo']],
            'plantillas' => ['modelo' => Plantilla::class, 'titulo' => 'Plantillas', 'cols' => ['nombre'], 'campos' => [
                'tipo_documento_id' => ['Tipo de documento', 'tipo', ['required', 'integer', 'exists:tipos_documento,id']],
                'nombre' => ['Nombre', 'text', ['required', 'string', 'max:150']],
                'contenido_html' => ['Contenido ({{fecha}}, {{expediente}}, {{solicitante_nombre}}, {{solicitante_run}}, {{folio}}, {{tramite}}, {{unidad}})', 'editor', ['required', 'string', 'max:200000']],
            ], 'unico' => []],
        ];
        abort_unless(isset($defs[$entidad]), 404);

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
            'sel' => $fila instanceof Tramite ? $fila->unidades()->pluck('unidades.id')->all() : [],
            'anexosTxt' => $fila instanceof Tramite ? $fila->anexos->map(fn ($a) => $a->nombre.'|'.($a->obligatorio ? 1 : 0))->implode("\n") : '',
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
        if ($entidad === 'usuarios') {
            $run = Run::normalizar((string) $r->input('run'));
            if ($run === null) {
                abort(422, 'RUN inválido');
            }
            $r->merge(['run' => $run]);
        }
        $d = $r->validate($this->reglas($def, $id));
        $d = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $d);
        if ($entidad === 'usuarios') {
            $d['puede_firmar'] = (bool) ($d['puede_firmar'] ?? false);
            if (in_array($d['rol'], ['admin', 'auditor'], true)) {
                $d['puede_firmar'] = false;
            }
        }
        if ($entidad === 'tipos') {
            $d['firma_alcalde'] = (bool) ($d['firma_alcalde'] ?? false);
        }
        if ($entidad === 'plantillas') {
            $d['contenido_html'] = $this->san->limpiar($d['contenido_html']);
        }
        DB::transaction(function () use ($def, $entidad, $id, $d) {
            $unidades = $d['unidades'] ?? null;
            $anexos = $d['anexos_txt'] ?? null;
            unset($d['unidades'], $d['anexos_txt']);
            $m = $id ? $def['modelo']::query()->findOrFail($id) : new $def['modelo'];
            $m->fill($d)->save();
            if ($m instanceof Tramite) {
                $m->unidades()->sync($unidades);
                TramiteAnexo::query()->where('tramite_id', $m->id)->update(['activo' => false]);
                foreach (preg_split('/\R/', (string) $anexos) ?: [] as $linea) {
                    if (trim($linea) === '') {
                        continue;
                    }
                    [$nombre, $ob] = array_pad(explode('|', $linea, 2), 2, '1');
                    TramiteAnexo::query()->create(['tramite_id' => $m->id, 'nombre' => mb_substr(trim($nombre), 0, 190), 'obligatorio' => trim($ob) !== '0', 'activo' => true]);
                }
            }
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
            'usuarios' => Usuario::query()->where('activo', true)->where('puede_firmar', true)->whereIn('rol', ['funcionario', 'secretario'])->orderBy('apellidos')->get(),
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
