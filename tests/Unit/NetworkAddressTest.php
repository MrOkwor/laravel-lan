<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use InvalidArgumentException;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use PHPUnit\Framework\TestCase;

final class NetworkAddressTest extends TestCase
{
    public function test_validates_and_normalizes_ipv4(): void
    {
        $address = new NetworkAddress('192.168.1.42');

        $this->assertSame('192.168.1.42', $address->ip);
        $this->assertSame(AddressFamily::IPv4, $address->family);
        $this->assertTrue($address->isIpv4());
        $this->assertFalse($address->isIpv6());
        $this->assertTrue($address->isPrivate());
        $this->assertFalse($address->isLoopback());
        $this->assertFalse($address->isLinkLocal());
        $this->assertTrue($address->isUsableLan());
    }

    public function test_recognizes_private_ipv4_subnets(): void
    {
        // 10.0.0.0/8
        $this->assertTrue((new NetworkAddress('10.0.0.1'))->isPrivate());
        $this->assertTrue((new NetworkAddress('10.255.255.254'))->isPrivate());

        // 172.16.0.0/12
        $this->assertTrue((new NetworkAddress('172.16.0.1'))->isPrivate());
        $this->assertTrue((new NetworkAddress('172.31.255.254'))->isPrivate());
        $this->assertFalse((new NetworkAddress('172.15.255.255'))->isPrivate());
        $this->assertFalse((new NetworkAddress('172.32.0.1'))->isPrivate());

        // 192.168.0.0/16
        $this->assertTrue((new NetworkAddress('192.168.0.1'))->isPrivate());
        $this->assertTrue((new NetworkAddress('192.168.254.254'))->isPrivate());
    }

    public function test_recognizes_loopback_and_link_local(): void
    {
        $loopback = new NetworkAddress('127.0.0.1');
        $this->assertTrue($loopback->isLoopback());
        $this->assertFalse($loopback->isPrivate());
        $this->assertFalse($loopback->isUsableLan());

        $linkLocal = new NetworkAddress('169.254.10.20');
        $this->assertTrue($linkLocal->isLinkLocal());
        $this->assertFalse($linkLocal->isPrivate());
        $this->assertFalse($linkLocal->isUsableLan());
    }

    public function test_recognizes_public_ips(): void
    {
        $googleDns = new NetworkAddress('8.8.8.8');
        $this->assertTrue($googleDns->isPublic());
        $this->assertFalse($googleDns->isPrivate());
        $this->assertFalse($googleDns->isUsableLan());

        $cloudflare = new NetworkAddress('1.1.1.1');
        $this->assertTrue($cloudflare->isPublic());
        $this->assertFalse($cloudflare->isPrivate());
    }

    public function test_handles_ipv6(): void
    {
        $ipv6 = new NetworkAddress('fe80::1ff:fe23:4567:890a');
        $this->assertTrue($ipv6->isIpv6());
        $this->assertFalse($ipv6->isIpv4());
        $this->assertTrue($linkLocal = $ipv6->isLinkLocal());
    }

    public function test_rejects_invalid_ip(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NetworkAddress('not.an.ip.address');
    }
}
