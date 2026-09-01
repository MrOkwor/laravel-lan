<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests;

use Mrokwor\LaravelLan\LaravelLanServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelLanServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('lan.port', 8000);
        $app['config']->set('lan.host', '0.0.0.0');
        $app['config']->set('lan.qr', true);
    }
}
