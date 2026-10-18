<?php

namespace App\Http\Controllers;

use App\Models\CuentaPrueba;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ClaveUnicaService;
use App\Support\Ambiente;
use App\Support\Espacio;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function ingreso()
    {
        return view('auth.ingreso', ['pruebas' => Ambiente::accesoPruebasActivo(), 'claveunica' => config('sgd.claveunica.mode') !== 'mock' || ! app()->isProduction()]);
    }

    // ---- Acceso de PRUEBAS con usuario y contraseña (solo SGD_AMBIENTE=pruebas y hasta la fecha configurada)
    public function pruebasForm()
    {
        abort_unless(Ambiente::accesoPruebasActivo(), 404);

        return view('auth.pruebas');
    }

    public function pruebasLogin(Request $request)
    {
        abort_unless(Ambiente::accesoPruebasActivo(), 404);
        $d = $request->validate(['run' => 'required|string|max:15', 'password' => 'required|string|max:200']);
        $run = Run::normalizar($d['run']);
        $clave = 'pruebas:'.hash('sha256', ($run ?? $d['run']).'|'.$request->ip());
        $claveRun = 'pruebas-run:'.hash('sha256', $run ?? $d['run']);
        if (RateLimiter::tooManyAttempts($clave, 5) || RateLimiter::tooManyAttempts($claveRun, 15)) {
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'pruebas_bloqueo', 'run' => $run ? Run::enmascarar($run) : null], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['run' => $d['run']])->with('error', 'Demasiados intentos. Espere 15 minutos.');
        }
        $c = $run ? CuentaPrueba::query()->where('run', $run)->where('activo', true)->first() : null;
        // comparación de costo constante aunque la cuenta no exista (sin enumeración por tiempo)
        $ok = Hash::check($d['password'], $c->password ?? '$2y$12$wugNHhvPcVl3Q/Ov6fLV.ekUSbZV.fXIZ1Wekxz6T1aMXX58cYswW');
        if (! $c || ! $ok) {
            RateLimiter::hit($clave, 900);
            RateLimiter::hit($claveRun, 900); // con tope por RUN aunque se roten las IP
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'pruebas_credenciales', 'run' => $run ? Run::enmascarar($run) : null], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['run' => $d['run']])->with('error', 'RUN o contraseña incorrectos.');
        }
        RateLimiter::clear($clave);
        RateLimiter::clear($claveRun);
        $c->forceFill(['ultimo_ingreso' => now()])->save();

        return $this->iniciar($request, $c->tipo, ['run' => $c->run, 'nombres' => $c->nombres, 'apellidos' => $c->apellidos]);
    }

    public function redirigir(Request $request, string $via, ClaveUnicaService $cu)
    {
        $state = bin2hex(random_bytes(32));
        $request->session()->put('cu', ['state' => hash('sha256', $state), 'via' => $via, 'exp' => time() + 600]);
        if (config('sgd.claveunica.mode') === 'mock') {
            return redirect()->route('dev.login', ['via' => $via, 'state' => $state]);
        }

        return redirect()->away($cu->urlAutorizacion($state));
    }

    public function callback(Request $request, ClaveUnicaService $cu)
    {
        $pend = $request->session()->pull('cu'); // de un solo uso
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        if (! is_array($pend) || $pend['exp'] < time() || $state === '' || ! hash_equals($pend['state'], hash('sha256', $state)) || $code === '') {
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'state_invalido'], false, ['run' => null, 'rol' => null]);

            return $this->fallo();
        }
        try {
            $id = $cu->identificar($code, $state);
        } catch (\Throwable) {
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'claveunica'], false, ['run' => null, 'rol' => null]);

            return $this->fallo();
        }

        return $this->iniciar($request, $pend['via'], $id);
    }

    /** @param array{run:string,nombres:string,apellidos:string} $id */
    private function iniciar(Request $request, string $via, array $id)
    {
        $u = null;
        if ($via === 'funcionario') {
            $u = Usuario::query()->where('run', $id['run'])->where('activo', true)->first();
            if (! $u) {
                $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'run_no_habilitado', 'run' => Run::enmascarar($id['run'])], false, ['run' => $id['run'], 'rol' => null]);

                return $this->fallo('Su RUN no está habilitado en el sistema. Contacte al administrador.');
            }
        }
        $pendiente = $request->session()->get('tramite_pendiente'); // se conserva solo para ciudadanos (ver abajo)
        $request->session()->invalidate(); // sesión nueva (anti fijación)
        $request->session()->regenerateToken();
        $request->session()->put(['login_at' => time(), 'last_seen' => time()]);
        if ($u !== null) {
            Auth::login($u);
            $u->forceFill(['ultimo_acceso' => now()])->save();
            $this->audit->registrar('auth.login', 'usuario', $u->id, ['via' => 'funcionario'], false, ['run' => $u->run, 'rol' => $u->rol]);

            if (Espacio::habilitado()) { // también es vecino(a): misma identidad acreditada, se elige el espacio en el inicio dual
                $request->session()->put('ciudadano', ['run' => $u->run, 'nombres' => $id['nombres'], 'apellidos' => $id['apellidos']]);

                return redirect()->route('espacio');
            }

            return redirect()->route('app.bandeja');
        }
        $request->session()->put('ciudadano', $id);
        $this->audit->registrar('auth.login', null, null, ['via' => 'ciudadano'], false, ['run' => $id['run'], 'rol' => 'ciudadano']);

        // retoma el trámite elegido en el catálogo: solo un id entero vigente, de un solo uso, y solo si sigue publicado
        if (is_array($pendiente) && ($pendiente['exp'] ?? 0) >= time()) {
            $t = Tramite::query()->publicados()->find((int) ($pendiente['id'] ?? 0));
            if ($t) {
                return redirect()->route('ciudadano.nueva', $t);
            }

            return redirect()->route('ciudadano.index')->with('error', 'El trámite que eligió ya no está disponible. Revise los trámites vigentes.');
        }

        return redirect()->route('ciudadano.index');
    }

    private function fallo(string $msg = 'No fue posible validar su identidad. Intente nuevamente.')
    {
        return redirect()->route('ingreso')->with('error', $msg);
    }

    public function salir(Request $request)
    {
        $this->audit->registrar('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }

    // ---- SOLO DESARROLLO (modo mock de ClaveÚnica; ruta inexistente en producción)
    public function devForm(Request $request)
    {
        abort_unless(config('sgd.claveunica.mode') === 'mock' && app()->environment(['local', 'testing']), 404);

        return view('auth.dev', ['via' => $request->query('via', 'ciudadano'), 'state' => (string) $request->query('state')]);
    }

    public function devLogin(Request $request)
    {
        abort_unless(config('sgd.claveunica.mode') === 'mock' && app()->environment(['local', 'testing']), 404);
        $d = $request->validate(['run' => 'required|string|max:15', 'nombres' => 'required|string|max:120', 'apellidos' => 'required|string|max:120', 'via' => 'required|in:ciudadano,funcionario', 'state' => 'required|string']);
        $pend = $request->session()->pull('cu');
        if (! is_array($pend) || ! hash_equals($pend['state'], hash('sha256', $d['state'])) || $pend['exp'] < time()) {
            return $this->fallo();
        }
        $run = Run::normalizar($d['run']);
        if (! $run) {
            return $this->fallo('RUN inválido.');
        }

        return $this->iniciar($request, $pend['via'], ['run' => $run, 'nombres' => $d['nombres'], 'apellidos' => $d['apellidos']]);
    }
}
