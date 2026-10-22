<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Admin\PermisosUsuarioService;
use App\Services\AuditoriaService;
use App\Services\Interop\DocDigitalDirectorioService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Matriz de permisos por usuario: ver el detalle de sus roles y permisos, agregar roles y conceder o denegar permisos individuales. */
class PermisosUsuarioController extends Controller
{
    public function __construct(private readonly PermisosUsuarioService $svc, private readonly AuditoriaService $audit) {}

    public function indice(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:80', 'rol' => 'nullable|string|max:30', 'estado' => 'nullable|in:activos,inactivos']);
        $q = Usuario::query()->orderBy('apellidos')->orderBy('nombres');
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombres', 'like', $like)->orWhere('apellidos', 'like', $like)->orWhere('run', 'like', $like)->orWhere('cargo', 'like', $like));
        }
        if (! empty($f['rol'])) {
            $rol = Rol::query()->where('clave', $f['rol'])->first();
            $q->where(fn ($w) => $w->where('rol', $f['rol'])->when($rol, fn ($x) => $x->orWhereIn('id', DB::table('usuario_roles')->where('rol_id', $rol->id)->select('usuario_id'))));
        }
        if (($f['estado'] ?? '') !== '') {
            $q->where('activo', $f['estado'] === 'activos');
        }
        $pag = $q->paginate(30)->withQueryString();
        $nombres = Rol::query()->pluck('nombre', 'clave');
        $filas = $pag->getCollection()->map(fn (Usuario $u) => ['u' => $u, 'r' => $this->svc->resumen($u)]);

        return view('app.admin.permisos_usuarios', ['pag' => $pag, 'filas' => $filas, 'f' => $f, 'nombres' => $nombres, 'roles' => Rol::query()->where('activo', true)->orderBy('nombre')->get(), 'total' => count(Permisos::catalogo())]);
    }

    public function detalle(Usuario $usuario)
    {
        return view('app.admin.usuario_permisos', [
            'u' => $usuario, 'matriz' => $this->svc->matriz($usuario), 'disponibles' => $this->svc->rolesDisponibles($usuario), 'adicionales' => $usuario->rolesAdicionales(),
            'nombres' => Rol::query()->pluck('nombre', 'clave'), 'propio' => request()->user()->id === $usuario->id, 'resumen' => $this->svc->resumen($usuario), 'dd' => app(DocDigitalDirectorioService::class)->de($usuario),
        ]);
    }

    public function guardar(Request $r, Usuario $usuario)
    {
        $d = $r->validate(['rol_extra' => 'nullable|array', 'rol_extra.*' => 'string|max:30', 'perm' => 'nullable|array', 'perm.*' => 'nullable|in:,conceder,denegar']);
        try {
            $this->svc->guardar($usuario, (array) ($d['rol_extra'] ?? []), (array) ($d['perm'] ?? []), $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.permisos.usuario', $usuario)->with('ok', 'Roles y permisos guardados.'.($r->user()->id === $usuario->id ? '' : ' Rigen desde el próximo ingreso de la persona.'));
    }

    /** Matriz completa (una fila por usuario, una columna por permiso) en CSV. Queda registrada en la auditoría. */
    public function csv(Request $r)
    {
        $this->audit->registrar('permisos.exportar', null, null, [], true);
        $catalogo = Permisos::catalogo();
        $nombres = Rol::query()->pluck('nombre', 'clave');

        return response()->streamDownload(function () use ($catalogo, $nombres) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF"); // UTF-8 con BOM para Excel
            fputcsv($o, array_merge(['RUN', 'Nombre', 'Cargo', 'Activo', 'Rol principal', 'Roles adicionales', 'Superadministrador'], array_keys($catalogo)), ';');
            Usuario::query()->orderBy('apellidos')->orderBy('nombres')->chunk(100, function ($us) use ($o, $catalogo, $nombres) {
                foreach ($us as $u) {
                    $adic = implode(' | ', array_map(fn ($c) => $nombres[$c] ?? $c, $u->rolesAdicionales()));
                    fputcsv($o, array_merge([
                        $u->run, PermisosUsuarioService::celda($u->nombreCompleto()), PermisosUsuarioService::celda((string) $u->cargo), $u->activo ? 'sí' : 'no', $nombres[$u->rol] ?? $u->rol, $adic, $u->esSuperadmin() ? 'sí' : 'no',
                    ], array_map(fn ($p) => $u->tienePermiso($p) ? 1 : 0, array_keys($catalogo))), ';');
                }
            });
            fclose($o);
        }, 'matriz-permisos-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
