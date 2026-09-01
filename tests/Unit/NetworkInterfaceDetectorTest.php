<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Network\Detectors\LinuxDetector;
use Mrokwor\LaravelLan\Network\Detectors\MacOsDetector;
use Mrokwor\LaravelLan\Network\Detectors\WindowsDetector;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Mrokwor\LaravelLan\Tests\Mocks\FakeInterfaceDetector;
use PHPUnit\Framework\TestCase;

final class NetworkInterfaceDetectorTest extends TestCase
{
    public function test_parses_windows_ipconfig_fixture(): void
    {
        $fixture = <<<TXT
Windows IP Configuration

   Host Name . . . . . . . . . . . . : DEV-PC
   Primary Dns Suffix  . . . . . . . : 

Wireless LAN adapter Wi-Fi:

   Connection-specific DNS Suffix  . : lan
   Description . . . . . . . . . . . : Intel(R) Wi-Fi 6 AX200 160MHz
   Physical Address. . . . . . . . . : 00-11-22-33-44-55
   DHCP Enabled. . . . . . . . . . . : Yes
   IPv4 Address. . . . . . . . . . . : 192.168.1.42(Preferred)
   Subnet Mask . . . . . . . . . . . : 255.255.255.0
   Default Gateway . . . . . . . . . : 192.168.1.1

Ethernet adapter vEthernet (WSL):

   Description . . . . . . . . . . . : Hyper-V Virtual Ethernet Adapter
   Physical Address. . . . . . . . . : 00-15-5D-00-01-02
   IPv4 Address. . . . . . . . . . . : 172.28.0.1(Preferred)

Ethernet adapter Ethernet:

   Media State . . . . . . . . . . . : Media disconnected
   Description . . . . . . . . . . . : Realtek PCIe GbE Family Controller
TXT;

        $detector = new WindowsDetector($fixture);
        $interfaces = $detector->detect();

        $this->assertCount(3, $interfaces);

        // Wi-Fi
        $wifi = $interfaces[0];
        $this->assertSame('Wi-Fi', $wifi->name);
        $this->assertSame(InterfaceType::Wifi, $wifi->type);
        $this->assertTrue($wifi->isUp);
        $this->assertTrue($wifi->isWireless);
        $this->assertFalse($wifi->isVirtual);
        $this->assertSame('192.168.1.42', $wifi->getPreferredIpv4()?->ip);

        // WSL Virtual adapter
        $wsl = $interfaces[1];
        $this->assertSame('vEthernet (WSL)', $wsl->name);
        $this->assertTrue($wsl->isVirtual);

        // Disconnected Ethernet
        $eth = $interfaces[2];
        $this->assertFalse($eth->isUp);
    }

    public function test_parses_macos_ifconfig_fixture(): void
    {
        $ifconfig = <<<TXT
lo0: flags=8049<UP,LOOPBACK,RUNNING,MULTICAST> mtu 16384
	inet 127.0.0.1 netmask 0xff000000 
	inet6 ::1 prefixlen 128 
en0: flags=8863<UP,BROADCAST,SMART,RUNNING,SIMPLEX,MULTICAST> mtu 1500
	ether 3c:22:fb:11:22:33 
	inet 192.168.1.55 netmask 0xffffff00 broadcast 192.168.1.255
	status: active
en1: flags=8963<UP,BROADCAST,SMART,RUNNING,PROMISC,SIMPLEX,MULTICAST> mtu 1500
	status: inactive
TXT;

        $networkSetup = <<<TXT
Hardware Port: Wi-Fi
Device: en0

Hardware Port: Ethernet
Device: en1
TXT;

        $detector = new MacOsDetector($ifconfig, $networkSetup);
        $interfaces = $detector->detect();

        $this->assertCount(3, $interfaces);

        $en0 = $interfaces[1];
        $this->assertSame('en0', $en0->name);
        $this->assertSame('en0 (Wi-Fi)', $en0->displayName);
        $this->assertTrue($en0->isUp);
        $this->assertSame('192.168.1.55', $en0->getPreferredIpv4()?->ip);

        $en1 = $interfaces[2];
        $this->assertFalse($en1->isUp);
    }

    public function test_parses_linux_ip_json_fixture(): void
    {
        $json = <<<JSON
[
  {
    "ifname": "lo",
    "flags": ["LOOPBACK", "UP"],
    "operstate": "UNKNOWN",
    "addr_info": [
      { "family": "inet", "local": "127.0.0.1" }
    ]
  },
  {
    "ifname": "wlan0",
    "flags": ["BROADCAST", "MULTICAST", "UP"],
    "operstate": "UP",
    "addr_info": [
      { "family": "inet", "local": "192.168.1.88" }
    ]
  },
  {
    "ifname": "docker0",
    "flags": ["BROADCAST", "MULTICAST", "UP"],
    "operstate": "UP",
    "addr_info": [
      { "family": "inet", "local": "172.17.0.1" }
    ]
  }
]
JSON;

        $detector = new LinuxDetector(rawJsonOutput: $json);
        $interfaces = $detector->detect();

        $this->assertCount(3, $interfaces);

        $wifi = $interfaces[1];
        $this->assertSame('wlan0', $wifi->name);
        $this->assertSame(InterfaceType::Wifi, $wifi->type);
        $this->assertSame('192.168.1.88', $wifi->getPreferredIpv4()?->ip);

        $docker = $interfaces[2];
        $this->assertTrue($docker->isVirtual);
    }

    public function test_ranks_wifi_and_ethernet_above_virtual_and_loopback(): void
    {
        $fakeDetector = new FakeInterfaceDetector([
            new NetworkInterface(
                name: 'docker0',
                displayName: 'docker0',
                type: InterfaceType::Virtual,
                addresses: [new NetworkAddress('172.17.0.1')],
                isUp: true,
                isVirtual: true,
            ),
            new NetworkInterface(
                name: 'lo',
                displayName: 'lo',
                type: InterfaceType::Loopback,
                addresses: [new NetworkAddress('127.0.0.1')],
                isUp: true,
                isLoopback: true,
            ),
            new NetworkInterface(
                name: 'wlan0',
                displayName: 'Wi-Fi',
                type: InterfaceType::Wifi,
                addresses: [new NetworkAddress('192.168.1.100')],
                isUp: true,
                isWireless: true,
            ),
            new NetworkInterface(
                name: 'eth0',
                displayName: 'Ethernet',
                type: InterfaceType::Ethernet,
                addresses: [new NetworkAddress('10.0.0.100')],
                isUp: true,
            ),
        ]);

        $detector = new NetworkInterfaceDetector([$fakeDetector]);
        $detected = $detector->detect();

        // Highest priority must be Wi-Fi, then Ethernet, then Docker, then loopback
        $this->assertSame('wlan0', $detected[0]->name);
        $this->assertSame('eth0', $detected[1]->name);

        $usable = $detector->detectUsableLanInterfaces();
        $this->assertCount(2, $usable);
        $this->assertSame('wlan0', $usable[0]->name);
        $this->assertSame('eth0', $usable[1]->name);
    }
}
