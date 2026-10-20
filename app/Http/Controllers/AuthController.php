<?php

namespace App\Http\Controllers;

use App\Models\CuentaPrueba;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ClaveUnicaService;
use App\Services\Directorio\DirectorioException;
use App\Services\Directorio\DirectorioFuncionarios;
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
        $usuario = DirectorioFuncionarios::loginUsuarioHabilitado();

        return view('auth.ingreso', ['usuario' => $usuario, 'pruebas' => Ambiente::accesoPruebasActivo() && ! $usuario, 'claveunica' => config('sgd.claveunica.mode') !== 'mock' || ! app()->isProduction()]);
    }

    // ---- Ingreso de funcionarios con «username» y contraseña del directorio (base sistemas_usuarios), sin ClaveÚnica
    public function usuarioForm()
    {
        abort_unless(DirectorioFuncionarios::loginUsuarioHabilitado(), 404);

        return view('auth.usuario');
    }

    public function usuarioLogin(Request $request, DirectorioFuncionarios $dir)
    {
        abort_unless(DirectorioFuncionarios::loginUsuarioHabilitado(), 404);
        $d = $request->validate(['username' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\-]+$/'], 'password' => 'required|string|max:200'], ['username.regex' => 'El usuario solo admite letras, números, punto, guion y guion bajo.']);
        $usuario = strtolower($d['username']);
        $clave = 'usuario:'.hash('sha256', $usuario.'|'.$request->ip());
        $claveU = 'usuario-nombre:'.hash('sha256', $usuario);
        if (RateLimiter::tooManyAttempts($clave, 5) || RateLimiter::tooManyAttempts($claveU, 15)) {
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'usuario_bloqueo'], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['username' => $d['username']])->with('error', 'Demasiados intentos. Espere 15 minutos.');
        }
        $falla = function (string $motivo) use ($clave, $claveU, $d) {
            RateLimiter::hit($clave, 900);
            RateLimiter::hit($claveU, 900);
            $this->audit->registrar('auth.fallo', null, null, ['motivo' => $motivo], false, ['run' => null, 'rol' => null]);

            return back()->withInput(['username' => $d['username']])->with('error', 'Usuario o contraseña incorrectos.');
        };
        try {
            $run = $dir->autenticar($d['username'], $d['password']);
        } catch (DirectorioException) {
            return back()->withInput(['username' => $d['username']])->with('error', 'No fue posible validar su ingreso en este momento. Intente nuevamente o use ClaveÚnica.');
        }
        if ($run === null) {
            return $falla('usuario_credenciales');
        }
        $u = $dir->resolver($run);
        if (! $u || $u->rolBase() === 'admin') { // la administración entra solo con ClaveÚnica y segundo factor
            return $falla($u ? 'usuario_admin_no_permitido' : 'usuario_sin_acceso');
        }
        RateLimiter::clear($clave);
        RateLimiter::clear($claveU);
        $request->session()->invalidate(); // sesión nueva (anti fijación)
        $request->session()->regenerateToken();
        $request->session()->put(['login_at' => time(), 'last_seen' => time()]);
        Auth::login($u);
        $u->forceFill(['ultimo_acceso' => now()])->save();
        $this->audit->registrar('auth.login', 'usuario', $u->id, ['via' => 'usuario_directorio'], false, ['run' => $u->run, 'rol' => $u->rol]);

        return redirect()->route('app.bandeja');
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

    public function redirigir(Request $request, ClaveUnicaService $cu)
    {
        $via = 'auto'; // funcionario(a) o vecino(a) se decide al volver de ClaveÚnica, según el directorio
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

        return $this->iniciar($request, $pend['via'], $id, $cu);
    }

    /** @param array{run:string,nombres:string,apellidos:string} $id */
    private function iniciar(Request $request, string $via, array $id, ?ClaveUnicaService $cu = null)
    {
        if ($via === 'auto') { // un solo ingreso: si el RUN figura como funcionario(a) (directorio o cuenta local) entra como tal y conserva su espacio de vecino(a)
            $via = app(DirectorioFuncionarios::class)->resolver($id['run']) ? 'funcionario' : 'ciudadano';
        }
        $u = null;
        if ($via === 'funcionario') {
            $u = app(DirectorioFuncionarios::class)->resolver($id['run']);
            if (! $u) {
                $this->audit->registrar('auth.fallo', null, null, ['motivo' => 'run_no_habilitado', 'run' => Run::enmascarar($id['run'])], false, ['run' => $id['run'], 'rol' => null]);

                $msg = 'Su RUN no está habilitado en el sistema. Contacte al administrador.';

                // el manual de ClaveÚnica pide cerrar también su sesión cuando el RUN autenticado no existe en el sistema
                return $cu !== null && config('sgd.claveunica.mode') !== 'mock' ? redirect()->away($cu->urlCierre())->with('error', $msg) : $this->fallo($msg);
            }
        }
        $pendiente = $request->session()->get('tramite_pendiente'); // se conserva solo para ciudadanos (ver abajo)
        $retornoPago = $request->session()->get('retorno_pago'); // idem: vuelve a la consulta de pago a proveedores
        $request->session()->invalidate(); // sesión nueva (anti fijación)
        $request->session()->regenerateToken();
        $request->session()->put(['login_at' => time(), 'last_seen' => time()] + ($cu !== null && config('sgd.claveunica.mode') !== 'mock' ? ['via_cu' => true] : []));
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

        if (is_array($retornoPago) && ($retornoPago['exp'] ?? 0) >= time()) { // vuelve a la consulta de pago a proveedores (destino fijo, no elegido por quien llama)
            return redirect()->route('proveedores.pagos');
        }

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

    public function salir(Request $request, ClaveUnicaService $cu)
    {
        $viaClaveUnica = (bool) $request->session()->get('via_cu');
        $this->audit->registrar('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // quien entró con ClaveÚnica también cierra su sesión allá (redirección a pantalla completa)
        return $viaClaveUnica ? redirect()->away($cu->urlCierre()) : redirect()->route('inicio');
    }

    // ---- SOLO DESARROLLO (modo mock de ClaveÚnica; ruta inexistente en producción)
    public function devForm(Request $request)
    {
        abort_unless(config('sgd.claveunica.mode') === 'mock' && app()->environment(['local', 'testing']), 404);

        return view('auth.dev', ['via' => $request->query('via', 'auto'), 'state' => (string) $request->query('state')]);
    }

    public function devLogin(Request $request)
    {
        abort_unless(config('sgd.claveunica.mode') === 'mock' && app()->environment(['local', 'testing']), 404);
        $d = $request->validate(['run' => 'required|string|max:15', 'nombres' => 'required|string|max:120', 'apellidos' => 'required|string|max:120', 'via' => 'required|in:ciudadano,funcionario,auto', 'state' => 'required|string']);
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
