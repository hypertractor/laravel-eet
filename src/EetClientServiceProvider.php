<?php

namespace Pomocnik\Eet;

use Illuminate\Support\ServiceProvider;
use Pomocnik\Eet\Certificates\CertificateManager;
use Pomocnik\Eet\Crypto\BkpGenerator;
use Pomocnik\Eet\Crypto\PkpGenerator;
use Pomocnik\Eet\Soap\EetSoapClient;
use Pomocnik\Eet\Xml\XmlBuilder;

class EetClientServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/eet.php' => config_path('eet.php'),
        ], 'eet-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'eet-migrations');

        $this->publishes([
            __DIR__.'/../resources/schema/' => storage_path('eet/schema'),
        ], 'eet-schema');

        $this->publishes([
            __DIR__.'/../certs/' => storage_path('eet/certs'),
        ], 'eet-certs');

        $this->mergeConfigFrom(
            __DIR__.'/../config/eet.php',
            'eet',
        );
    }

    public function register(): void
    {
        $this->registerBindings();
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(CertificateManager::class, function ($app) {
            $path = config('eet.certificate.path');
            $password = config('eet.certificate.password');

            if (! $path || ! $password) {
                // Vratit fallback pro testovani bez certifikatu
                return new class ($app) {
                    public function __construct(private $app) {}

                    public function __call(string $method, array $args)
                    {
                        throw new \Pomocnik\Eet\Exceptions\CertificateException(
                            'EET certificate not configured. Set EET_CERTIFICATE_PATH and EET_CERTIFICATE_PASSWORD in your .env file.'
                        );
                    }
                };
            }

            return new CertificateManager($path, $password);
        });

        $this->app->singleton(XmlBuilder::class);

        $this->app->singleton(PkpGenerator::class);

        $this->app->singleton(BkpGenerator::class);

        $this->app->singleton(EetSoapClient::class, function ($app) {
            return new EetSoapClient(
                $app->make(CertificateManager::class),
                $app->make(XmlBuilder::class),
            );
        });

        $this->app->singleton(\Pomocnik\Eet\Services\EetService::class, function ($app) {
            return new \Pomocnik\Eet\Services\EetService(
                $app->make(CertificateManager::class),
                $app->make(EetSoapClient::class),
                $app->make(PkpGenerator::class),
                $app->make(BkpGenerator::class),
            );
        });
    }
}
