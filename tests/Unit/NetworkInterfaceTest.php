<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use PHPUnit\Framework\TestCase;

final class NetworkInterfaceTest extends TestCase
{
    public function test_identifies_usable_lan_interface(): void
    {
        $iface = new NetworkInterface(
            name: 'en0',
            displayName: 'Wi-Fi (en0)',
            type: InterfaceType::Wifi,
            addresses: [
                new NetworkAddress('192.168.1.42'),
                new NetworkAddress('fe80::1'),
            ],
            isUp: true,
            isWireless: true,
        );

        $this->assertTrue($iface->isUsableLan());
        $this->assertTrue($iface->hasPrivateIpv4());
        $this->assertSame('192.168.1.42', $iface->getPreferredIpv4()?->ip);
    }

    public function test_rejects_down_interface(): void
    {
        $iface = new NetworkInterface(
            name: 'eth0',
            displayName: 'Ethernet',
            type: InterfaceType::Ethernet,
            addresses: [new NetworkAddress('10.0.0.5')],
            isUp: false,
        );

        $this->assertFalse($iface->isUsableLan());
    }

    public function test_rejects_virtual_and_loopback_interface(): void
    {
        $virtual = new NetworkInterface(
            name: 'docker0',
            displayName: 'docker0',
            type: InterfaceType::Virtual,
            addresses: [new NetworkAddress('172.17.0.1')],
            isUp: true,
            isVirtual: true,
        );

        $this->assertFalse($virtual->isUsableLan());

        $loopback = new NetworkInterface(
            name: 'lo',
            displayName: 'Loopback',
            type: InterfaceType::Loopback,
            addresses: [new NetworkAddress('127.0.0.1')],
            isUp: true,
            isLoopback: true,
        );

        $this->assertFalse($loopback->isUsableLan());
    }
}
