<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\Documento;
use App\Models\Expediente;
use App\Policies\DocumentoPolicy;
use App\Policies\ExpedientePolicy;
use App\Services\Admin\AparienciaService;
use App\Services\Admin\ApiConfigService;
use App\Services\Admin\MenuService;
use App\Services\Casilla\CasillaAdapter;
use App\Services\ClaveUnicaService;
use App\Services\FileVault;
use App\Services\Firma\FirmaClient;
use App\Services\Firma\HttpFirmaClient;
use App\Services\Firma\MockFirmaClient;
use App\Services\Interop\DocDigital\DocDigitalClient;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\Interop\DocDigital\MockDocDigitalClient;
use App\Services\Sistema\ConexionesService;
use App\Support\Ambiente;
use App\Support\Espacio;
use App\Support\HashedDatabaseSessionHandler;
use App\Support\ProductionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuService::class);
        $this->app->singleton(FileVault::class, fn () => FileVault::fromConfig());
        $this->app->singleton(FirmaClient::class, function () {
            $c = config('sgd.firmagob');

            return $c['mode'] === 'mock' ? new MockFirmaClient : new HttpFirmaClient($c, $c['mode']);
        });
        $this->app->singleton(ClaveUnicaService::class, fn () => new ClaveUnicaService(config('sgd.claveunica')));
        $this->app->singleton(DocDigitalClient::class, function () {
            $c = config('sgd.docdigital');

            return match ($c['mode']) {
                'mock' => new MockDocDigitalClient,
                'demo', 'prod' => new HttpDocDigitalClient($c, $c['mode']),
                default => new class implements DocDigitalClient
                {
                    public function enviar(string $pdf, string $nombre, array $meta): array
                    {
                        throw new DocDigitalException('DocDigital está en modo outbox: registre la evidencia manualmente.');
                    }

                    public function acusarRecibo(string $id): array
                    {
                        throw new DocDigitalException('DocDigital está en modo outbox: registre el acuse manualmente.');
                    }

                    public function devolver(string $id, string $motivo): array
                    {
                        throw new DocDigitalException('DocDigital está en modo outbox: registre la devolución manualmente en DocDigital.');
                    }

                    public function recibidos(): array
                    {
                        return [];
                    }
                },
            };
        });
        $this->app->singleton(CasillaAdapter::class, fn () => new CasillaAdapter(config('sgd.casilla')));
    }

    public function boot(): void
    {
        ApiConfigService::aplicar(); // la configuración de BD prevalece sobre .env
        Ambiente::aplicarOverride();
        ProductionGuard::verificar();
        Paginator::useBootstrapFour();

        // Conexiones SALIENTES (integraciones): host de destino, sin URL completa, cabeceras ni cuerpo
        Http::globalMiddleware(fn (callable $handler) => function ($request, array $options) use ($handler) {
            $host = (string) $request->getUri()->getHost();
            $anotar = function (bool $error) use ($host) {
                try {
                    if (config('sgd.registrar_conexiones') && $host !== '') {
                        ConexionesService::registrar('saliente', strtolower($host), 'integracion', $error);
                    }
                } catch (\Throwable) {
                }
            };

            try {
                $promesa = $handler($request, $options);
            } catch (\Throwable $e) { // fallo síncrono (p. ej. simulaciones)
                $anotar(true);

                throw $e;
            }

            return $promesa->then(function ($resp) use ($anotar) {
                $anotar($resp->getStatusCode() >= 400);

                return $resp;
            }, function ($e) use ($anotar) {
                $anotar(true);

                throw $e;
            });
        });

        Session::extend('sgd_database', function ($app) {
            $c = $app['config']['session'];

            return new HashedDatabaseSessionHandler($app['db']->connection($c['connection']), $c['table'], $c['lifetime'], $app);
        });
        $this->app->afterResolving('mail.manager', fn ($m) => $m->extend('gmail_api', fn () => new GmailApiTransport(config('sgd.google'))));

        // Marca (colores, encabezado, pie, logo) y menú configurables: datos para el layout, tolerantes a BD sin migrar.
        View::composer(['layouts.app', 'partials.accesos'], function ($view) {
            try {
                $u = auth()->user();
                $espacio = Espacio::activo();
                $area = Espacio::dual() ? ($espacio ?? 'publico') : ($u ? 'app' : (session('ciudadano') ? 'ciudadano' : 'publico'));
                $marca = app(AparienciaService::class)->datos();
                $menu = app(MenuService::class)->items($area, $u);
            } catch (\Throwable) {
                $marca = AparienciaService::DEFECTO + ['version' => '0'];
                $menu = [];
                $espacio = null;
            }
            $view->with(['marca' => $marca, 'menuItems' => $menu, 'espacio' => $espacio, 'dual' => Espacio::dual()]);
        });
        View::composer(['pdf.documento', 'pdf.comprobante'], function ($view) {
            try {
                $view->with('logoPdf', app(AparienciaService::class)->logoDataUri());
            } catch (\Throwable) {
                $view->with('logoPdf', null);
            }
        });

        Gate::policy(Expediente::class, ExpedientePolicy::class);
        Gate::policy(Documento::class, DocumentoPolicy::class);

        $ip = fn (Request $r) => $r->ip();
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($ip($r)));
        RateLimiter::for('envio', fn (Request $r) => Limit::perMinute(10)->by($r->session()->get('ciudadano.run', $ip($r))));
        RateLimiter::for('publico', fn (Request $r) => Limit::perMinute((int) config('sgd.validar_rate_per_minute'))->by($ip($r)));
        RateLimiter::for('firma', fn (Request $r) => Limit::perMinute(10)->by((string) $r->user()?->id));
        RateLimiter::for('interop', fn (Request $r) => Limit::perMinute(10)->by((string) $r->user()?->id));
        RateLimiter::for('llm', fn (Request $r) => [
            Limit::perMinute((int) config('sgd.llm.per_minute'))->by('llm-m'.$r->user()?->id),
            Limit::perDay((int) config('sgd.llm.per_day'))->by('llm-d'.$r->user()?->id),
        ]);
    }
}
