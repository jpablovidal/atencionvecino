<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Usuarios conectados: sesiones vigentes (funcionarios y ciudadanos) y cierre forzado. */
class SesionesController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function index(Request $r)
    {
        $propia = hash('sha256', $r->session()->getId());
        $vigentes = DB::table('sessions')->where('last_activity', '>', time() - (int) config('session.lifetime') * 60)->orderByDesc('last_activity')->limit(500)->get();
        $usuarios = Usuario::query()->whereIn('id', $vigentes->pluck('user_id')->filter()->all())->get()->keyBy('id');
        $filas = $vigentes->map(function ($s) use ($usuarios, $propia) {
            $d = self::payload((string) $s->payload);
            $u = $s->user_id ? $usuarios->get($s->user_id) : null;

            return [
                'id' => $s->id, 'ref' => substr($s->id, 0, 8), 'propia' => hash_equals($propia, $s->id),
                'tipo' => $u ? 'Funcionario' : (isset($d['ciudadano']) ? 'Ciudadano' : 'Anónimo'),
                'nombre' => $u ? trim($u->nombres.' '.$u->apellidos) : (isset($d['ciudadano']['run']) ? 'RUN '.Run::enmascarar((string) $d['ciudadano']['run']) : '—'),
                'rol' => $u?->rol, 'ip' => $s->ip_address, 'agente' => mb_substr((string) $s->user_agent, 0, 80),
                'inicio' => isset($d['login_at']) ? date('d-m-Y H:i', (int) $d['login_at']) : '—', 'actividad' => date('d-m-Y H:i:s', (int) $s->last_activity),
            ];
        })->filter(fn ($f) => $f['tipo'] !== 'Anónimo')->values();

        return view('app.sesiones', ['filas' => $filas, 'puedeCerrar' => $r->user()->tienePermiso('sesiones.cerrar'), 'minutos' => (int) config('session.lifetime')]);
    }

    public function cerrar(Request $r, string $id)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $id) === 1, 404);
        if (hash_equals(hash('sha256', $r->session()->getId()), $id)) {
            return back()->with('error', 'Para cerrar su propia sesión use «Salir».');
        }
        $s = DB::table('sessions')->where('id', $id)->first();
        abort_if($s === null, 404);
        DB::table('sessions')->where('id', $id)->delete();
        $this->audit->registrar('sesion.cerrar_forzado', 'usuario', $s->user_id, ['sesion' => substr($id, 0, 8)], true);

        return back()->with('ok', 'Sesión cerrada.');
    }

    /** @return array<string,mixed> */
    public static function payload(string $raw): array
    {
        $bin = base64_decode($raw, true);
        $d = $bin === false ? false : @unserialize($bin, ['allowed_classes' => false]);

        return is_array($d) ? $d : [];
    }
}
