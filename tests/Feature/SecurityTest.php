<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Feature;

use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Mrokwor\LaravelLan\Network\NetworkSelector;
use Mrokwor\LaravelLan\Tests\Mocks\FakeInterfaceDetector;
use Mrokwor\LaravelLan\Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function test_blocks_execution_in_production_environment_by_default(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('lan', ['--json' => true])
            ->assertFailed()
            ->expectsOutputToContain("APP_ENV is set to 'production'");
    }

    public function test_allows_execution_in_production_with_force_flag(): void
    {
        $this->app['env'] = 'production';

        $fakeDetector = new FakeInterfaceDetector([
            new NetworkInterface(
                name: 'wlan0',
                displayName: 'Wi-Fi',
                type: InterfaceType::Wifi,
                addresses: [new NetworkAddress('192.168.1.77')],
                isUp: true,
            ),
        ]);

        $detector = new NetworkInterfaceDetector([$fakeDetector]);
        $this->app->instance(NetworkInterfaceDetector::class, $detector);
        $this->app->instance(NetworkSelector::class, new NetworkSelector($detector));

        $this->artisan('lan', ['--json' => true, '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"ip": "192.168.1.77"');
    }
}
