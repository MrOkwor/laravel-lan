<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Mrokwor\LaravelLan\Network\NetworkSelector;
use Mrokwor\LaravelLan\Tests\Mocks\FakeInterfaceDetector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NetworkSelectorTest extends TestCase
{
    public function test_selects_automatically_when_single_interface_available(): void
    {
        $iface = new NetworkInterface(
            name: 'en0',
            displayName: 'Wi-Fi',
            type: InterfaceType::Wifi,
            addresses: [new NetworkAddress('192.168.1.42')],
            isUp: true,
        );

        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([$iface])]);
        $selector = new NetworkSelector($detector);

        $selection = $selector->select();

        $this->assertSame('en0', $selection['interface']->name);
        $this->assertSame('192.168.1.42', $selection['address']->ip);
    }

    public function test_selects_highest_priority_when_multiple_and_non_interactive(): void
    {
        $wifi = new NetworkInterface(
            name: 'wlan0',
            displayName: 'Wi-Fi',
            type: InterfaceType::Wifi,
            addresses: [new NetworkAddress('192.168.1.42')],
            isUp: true,
            isWireless: true,
        );

        $eth = new NetworkInterface(
            name: 'eth0',
            displayName: 'Ethernet',
            type: InterfaceType::Ethernet,
            addresses: [new NetworkAddress('10.0.0.5')],
            isUp: true,
        );

        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([$eth, $wifi])]);
        $selector = new NetworkSelector($detector);

        $selection = $selector->select();

        $this->assertSame('wlan0', $selection['interface']->name);
        $this->assertSame('192.168.1.42', $selection['address']->ip);
    }

    public function test_allows_manual_override_by_name(): void
    {
        $wifi = new NetworkInterface(
            name: 'wlan0',
            displayName: 'Wi-Fi',
            type: InterfaceType::Wifi,
            addresses: [new NetworkAddress('192.168.1.42')],
            isUp: true,
        );

        $eth = new NetworkInterface(
            name: 'eth0',
            displayName: 'Ethernet',
            type: InterfaceType::Ethernet,
            addresses: [new NetworkAddress('10.0.0.5')],
            isUp: true,
        );

        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([$wifi, $eth])]);
        $selector = new NetworkSelector($detector);

        $selection = $selector->select(preferredInterface: 'eth0');

        $this->assertSame('eth0', $selection['interface']->name);
        $this->assertSame('10.0.0.5', $selection['address']->ip);
    }

    public function test_allows_manual_override_by_ip(): void
    {
        $wifi = new NetworkInterface(
            name: 'wlan0',
            displayName: 'Wi-Fi',
            type: InterfaceType::Wifi,
            addresses: [new NetworkAddress('192.168.1.42')],
            isUp: true,
        );

        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([$wifi])]);
        $selector = new NetworkSelector($detector);

        $selection = $selector->select(preferredInterface: '192.168.1.42');

        $this->assertSame('wlan0', $selection['interface']->name);
        $this->assertSame('192.168.1.42', $selection['address']->ip);
    }

    public function test_throws_when_preferred_interface_not_found(): void
    {
        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([])]);
        $selector = new NetworkSelector($detector);

        $this->expectException(RuntimeException::class);
        $selector->select(preferredInterface: 'nonexistent');
    }

    public function test_throws_when_no_usable_interface_found(): void
    {
        $detector = new NetworkInterfaceDetector([new FakeInterfaceDetector([])]);
        $selector = new NetworkSelector($detector);

        $this->expectException(RuntimeException::class);
        $selector->select();
    }
}
