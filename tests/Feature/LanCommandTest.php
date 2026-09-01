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

final class LanCommandTest extends TestCase
{
    public function test_json_flag_outputs_valid_network_payload(): void
    {
        $fakeDetector = new FakeInterfaceDetector([
            new NetworkInterface(
                name: 'wlan0',
                displayName: 'Wi-Fi',
                type: InterfaceType::Wifi,
                addresses: [new NetworkAddress('192.168.1.77')],
                isUp: true,
                isWireless: true,
            ),
        ]);

        $detector = new NetworkInterfaceDetector([$fakeDetector]);
        $this->app->instance(NetworkInterfaceDetector::class, $detector);
        $this->app->instance(NetworkSelector::class, new NetworkSelector($detector));

        $this->artisan('lan', ['--json' => true, '--port' => '58988'])
            ->assertSuccessful()
            ->expectsOutputToContain('"host": "0.0.0.0"')
            ->expectsOutputToContain('"port": 58988')
            ->expectsOutputToContain('"interface": "wlan0"')
            ->expectsOutputToContain('"ip": "192.168.1.77"')
            ->expectsOutputToContain('"local_url": "http://127.0.0.1:58988"')
            ->expectsOutputToContain('"lan_url": "http://192.168.1.77:58988"');
    }

    public function test_respects_custom_interface_flag(): void
    {
        $fakeDetector = new FakeInterfaceDetector([
            new NetworkInterface(
                name: 'wlan0',
                displayName: 'Wi-Fi',
                type: InterfaceType::Wifi,
                addresses: [new NetworkAddress('192.168.1.77')],
                isUp: true,
            ),
            new NetworkInterface(
                name: 'eth0',
                displayName: 'Ethernet',
                type: InterfaceType::Ethernet,
                addresses: [new NetworkAddress('10.0.0.88')],
                isUp: true,
            ),
        ]);

        $detector = new NetworkInterfaceDetector([$fakeDetector]);
        $this->app->instance(NetworkInterfaceDetector::class, $detector);
        $this->app->instance(NetworkSelector::class, new NetworkSelector($detector));

        $this->artisan('lan', ['--json' => true, '--interface' => 'eth0', '--port' => '58989'])
            ->assertSuccessful()
            ->expectsOutputToContain('"interface": "eth0"')
            ->expectsOutputToContain('"ip": "10.0.0.88"')
            ->expectsOutputToContain('"lan_url": "http://10.0.0.88:58989"');
    }
}
