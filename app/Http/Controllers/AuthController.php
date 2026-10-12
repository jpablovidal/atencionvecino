<?php

namespace App\Http\Controllers;

use App\Models\CuentaPrueba;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Services\AuditoriaService;
use App\Services\ClaveUnicaService;
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
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'pruebas_bloqueo', 'run' => $run ? Run::enmascarar($run) : null], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['run' => $d['run']])->with('error', 'Demasiados intentos. Espere 15 minutos.');
        }
        $c = $run ? CuentaPrueba::query()->where('run', $run)->where('activo', true)->first() : null;
        // comparación de costo constante aunque la cuenta no exista (sin enumeración por tiempo)
        $ok = Hash::check($d['password'], $c->password ?? '$2y$12$wugNHhvPcVl3Q/Ov6fLV.ekUSbZV.fXIZ1Wekxz6T1aMXX58cYswW');
        if (! $c || ! $ok) {
            RateLimiter::hit($clave, 900);
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'pruebas_credenciales', 'run' => $run ? Run::enmascarar($run) : null], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['run' => $d['run']])->with('error', 'RUN o contraseña incorrectos.');
        }
        RateLimiter::clear($clave);
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
        $request->session()->invalidate(); // sesión nueva (anti fijación)
        $request->session()->regenerateToken();
        $request->session()->put(['login_at' => time(), 'last_seen' => time()]);
        if ($u !== null) {
            Auth::login($u);
            $u->forceFill(['ultimo_acceso' => now()])->save();
            $this->audit->registrar('auth.login', 'usuario', $u->id, ['via' => 'funcionario'], false, ['run' => $u->run, 'rol' => $u->rol]);

            return redirect()->route('app.bandeja');
        }
        $request->session()->put('ciudadano', $id);
        $this->audit->registrar('auth.login', null, null, ['via' => 'ciudadano'], false, ['run' => $id['run'], 'rol' => 'ciudadano']);

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
        abort_unless(config('sgd.claveunica.mode') === 'mock' && ! app()->isProduction(), 404);

        return view('auth.dev', ['via' => $request->query('via', 'ciudadano'), 'state' => (string) $request->query('state')]);
    }

    public function devLogin(Request $request)
    {
        abort_unless(config('sgd.claveunica.mode') === 'mock' && ! app()->isProduction(), 404);
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
