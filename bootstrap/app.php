<?php

use App\Http\Middleware\EnsureCiudadano;
use App\Http\Middleware\EnsureFuncionario;
use App\Http\Middleware\EnsurePermiso;
use App\Http\Middleware\EnsureRol;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionLimits;
use App\Services\AuditoriaService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
        // Solo se atienden peticiones cuyo Host sea el de APP_URL (evita envenenamiento del encabezado Host en enlaces y correos)
        $middleware->trustHosts(at: function (): array {
            $h = parse_url((string) env('APP_URL'), PHP_URL_HOST);

            return $h ? ['^'.preg_quote($h, '#').'$'] : [];
        }, subdomains: false);
        $middleware->encryptCookies(except: []);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SessionLimits::class]);
        $middleware->alias([
            'ciudadano' => EnsureCiudadano::class,
            'funcionario' => EnsureFuncionario::class,
            'rol' => EnsureRol::class,
            'permiso' => EnsurePermiso::class,
            'nostore' => NoStore::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->user() !== null || $request->hasSession() && $request->session()->has('ciudadano')) { // anónimos: no se registra (evita inflar la bitácora)
                app(AuditoriaService::class)->registrar('seguridad.csrf_fallido', null, null, ['ruta' => $request->path()]);
            }

            return response()->view('errores.generico', ['codigo' => 419, 'mensaje' => 'La sesión expiró o el formulario no es válido. Vuelva a intentarlo.'], 419);
        });
        $exceptions->render(function (Throwable $e, $request) {
            if ($e instanceof HttpExceptionInterface) {
                $c = $e->getStatusCode();
                // las respuestas 403/419 de visitantes anónimos no se registran: evita inflar la bitácora (y su bloqueo global) desde fuera
                $identificado = $request->user() !== null || $request->hasSession() && $request->session()->has('ciudadano');
                if (in_array($c, [403, 419], true) && $identificado) {
                    try {
                        app(AuditoriaService::class)->registrar($c === 419 ? 'seguridad.csrf_fallido' : 'seguridad.acceso_denegado', null, null, ['ruta' => $request->path(), 'metodo' => $request->method()]);
                    } catch (Throwable) {
                    }
                }
                $m = [403 => 'No tiene permiso para realizar esta acción.', 404 => 'No encontramos lo que busca.', 429 => 'Demasiadas solicitudes. Espere un momento.'][$c] ?? 'No fue posible procesar la solicitud.';

                return response()->view('errores.generico', ['codigo' => $c, 'mensaje' => $m], $c, $e->getHeaders());
            }
            if (! config('app.debug') && ! $e instanceof ValidationException && ! $e instanceof AuthenticationException) {
                try {
                    app(AuditoriaService::class)->registrar('seguridad.error_500', null, null, ['clase' => class_basename($e)]);
                } catch (Throwable) {
                }
                report($e);

                return response()->view('errores.generico', ['codigo' => 500, 'mensaje' => 'Ocurrió un error interno. Intente nuevamente más tarde.'], 500);
            }

            return null;
        });
    })->create();
