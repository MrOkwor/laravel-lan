<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan;

use Illuminate\Support\ServiceProvider;
use Mrokwor\LaravelLan\Commands\LanCommand;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticRunner;
use Mrokwor\LaravelLan\Network\LanUrlBuilder;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Mrokwor\LaravelLan\Network\NetworkSelector;
use Mrokwor\LaravelLan\Network\PortChecker;
use Mrokwor\LaravelLan\QR\QrCodeGenerator;

final class LaravelLanServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lan.php',
            'lan'
        );

        $this->app->singleton(NetworkInterfaceDetector::class, function () {
            return new NetworkInterfaceDetector();
        });

        $this->app->singleton(NetworkSelector::class, function ($app) {
            return new NetworkSelector($app->make(NetworkInterfaceDetector::class));
        });

        $this->app->singleton(PortChecker::class, function () {
            return new PortChecker();
        });

        $this->app->singleton(LanUrlBuilder::class, function () {
            return new LanUrlBuilder();
        });

        $this->app->singleton(QrCodeGenerator::class, function () {
            return new QrCodeGenerator();
        });

        $this->app->singleton(DiagnosticRunner::class, function () {
            return new DiagnosticRunner();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/lan.php' => config_path('lan.php'),
            ], 'laravel-lan-config');

            $this->commands([
                LanCommand::class,
            ]);
        }
    }
}
