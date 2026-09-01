<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Detectors;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Throwable;

final class PhpNativeDetector implements InterfaceDetectorInterface
{
    public function detect(): array
    {
        if (!function_exists('net_get_interfaces')) {
            return [];
        }

        try {
            $raw = @net_get_interfaces();
            if (!is_array($raw) || empty($raw)) {
                return [];
            }

            $interfaces = [];

            foreach ($raw as $name => $details) {
                $isUp = $details['up'] ?? true;
                $mac = $details['mac'] ?? null;
                $unicast = $details['unicast'] ?? [];

                $addresses = [];
                foreach ($unicast as $entry) {
                    $ip = $entry['address'] ?? null;
                    if (!$ip || !is_string($ip)) {
                        continue;
                    }

                    $family = match ($entry['family'] ?? null) {
                        AF_INET => AddressFamily::IPv4,
                        AF_INET6 => AddressFamily::IPv6,
                        default => null,
                    };

                    $netmask = $entry['netmask'] ?? null;

                    try {
                        $addresses[] = new NetworkAddress($ip, $family, $netmask);
                    } catch (Throwable) {
                        // Skip malformed address
                    }
                }

                $lowerName = strtolower((string) $name);
                $isLoopback = $lowerName === 'lo' || str_contains($lowerName, 'loopback');
                $isWireless = str_contains($lowerName, 'wi-fi') || str_contains($lowerName, 'wifi') || str_starts_with($lowerName, 'wl');
                $isVirtual = str_contains($lowerName, 'docker')
                    || str_contains($lowerName, 'veth')
                    || str_contains($lowerName, 'br-')
                    || str_contains($lowerName, 'vmnet')
                    || str_contains($lowerName, 'vbox')
                    || str_contains($lowerName, 'hyper-v')
                    || str_contains($lowerName, 'wsl')
                    || str_contains($lowerName, 'tailscale')
                    || str_contains($lowerName, 'wg');

                $type = match (true) {
                    $isLoopback => InterfaceType::Loopback,
                    str_contains($lowerName, 'tun') || str_contains($lowerName, 'tap') || str_contains($lowerName, 'vpn') => InterfaceType::Vpn,
                    $isWireless => InterfaceType::Wifi,
                    $isVirtual => InterfaceType::Virtual,
                    str_contains($lowerName, 'eth') || str_contains($lowerName, 'en') || str_contains($lowerName, 'ethernet') => InterfaceType::Ethernet,
                    default => InterfaceType::Other,
                };

                $interfaces[] = new NetworkInterface(
                    name: (string) $name,
                    displayName: (string) $name,
                    type: $type,
                    addresses: $addresses,
                    isUp: (bool) $isUp,
                    isWireless: $isWireless,
                    isVirtual: $isVirtual,
                    isLoopback: $isLoopback,
                    macAddress: $mac ? (string) $mac : null,
                );
            }

            return $interfaces;
        } catch (Throwable) {
            return [];
        }
    }
}
