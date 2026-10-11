<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\Documento;
use App\Models\Expediente;
use App\Policies\DocumentoPolicy;
use App\Policies\ExpedientePolicy;
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
use App\Support\HashedDatabaseSessionHandler;
use App\Support\ProductionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
        ProductionGuard::verificar();
        Paginator::useBootstrapFour();

        Session::extend('sgd_database', function ($app) {
            $c = $app['config']['session'];

            return new HashedDatabaseSessionHandler($app['db']->connection($c['connection']), $c['table'], $c['lifetime'], $app);
        });
        $this->app->afterResolving('mail.manager', fn ($m) => $m->extend('gmail_api', fn () => new GmailApiTransport(config('sgd.google'))));

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
