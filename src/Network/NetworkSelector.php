<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

use Closure;
use RuntimeException;

final class NetworkSelector
{
    public function __construct(
        private NetworkInterfaceDetector $detector
    ) {
    }

    /**
     * Select the best interface or the requested interface.
     *
     * @param string|null $preferredInterface Name or IP specified by user
     * @param Closure|null $promptCallback Callback fn(array $options, $default): int when interactive choice is needed
     * @return array{interface: NetworkInterface, address: NetworkAddress}
     */
    public function select(?string $preferredInterface = null, ?Closure $promptCallback = null): array
    {
        $allInterfaces = $this->detector->detect();

        // 1. Explicit preference specified
        if ($preferredInterface !== null && trim($preferredInterface) !== '') {
            $preferred = trim($preferredInterface);

            // Check if matches an IP directly
            foreach ($allInterfaces as $iface) {
                foreach ($iface->addresses as $address) {
                    if ($address->ip === $preferred) {
                        return [
                            'interface' => $iface,
                            'address' => $address,
                        ];
                    }
                }
            }

            // Check if matches interface name (case-insensitive)
            foreach ($allInterfaces as $iface) {
                if (strcasecmp($iface->name, $preferred) === 0 || strcasecmp($iface->displayName, $preferred) === 0) {
                    $addr = $iface->getPreferredIpv4() ?? $iface->addresses[0] ?? null;
                    if ($addr === null) {
                        throw new RuntimeException("Interface '{$preferred}' has no configured IP address.");
                    }

                    return [
                        'interface' => $iface,
                        'address' => $addr,
                    ];
                }
            }

            // Partial match on interface name
            foreach ($allInterfaces as $iface) {
                if (stripos($iface->name, $preferred) !== false || stripos($iface->displayName, $preferred) !== false) {
                    $addr = $iface->getPreferredIpv4() ?? $iface->addresses[0] ?? null;
                    if ($addr !== null) {
                        return [
                            'interface' => $iface,
                            'address' => $addr,
                        ];
                    }
                }
            }

            throw new RuntimeException("Network interface or address '{$preferred}' was not found.");
        }

        // 2. Auto-detect usable LAN interfaces
        $usable = $this->detector->detectUsableLanInterfaces();

        if (empty($usable)) {
            // Fallback: check if there is any interface with IPv4 address even if virtual or other
            $anyIpv4 = [];
            foreach ($allInterfaces as $iface) {
                if (!$iface->isLoopback && $iface->isUp && $iface->getPreferredIpv4() !== null) {
                    $anyIpv4[] = $iface;
                }
            }

            if (!empty($anyIpv4)) {
                $chosen = $anyIpv4[0];
                return [
                    'interface' => $chosen,
                    'address' => $chosen->getPreferredIpv4() ?? $chosen->addresses[0],
                ];
            }

            throw new RuntimeException(
                "No active LAN network interface found.\n" .
                "Please ensure your computer is connected to a local network (Wi-Fi or Ethernet)."
            );
        }

        // If exactly one usable interface exists, return it immediately
        if (count($usable) === 1) {
            $chosen = $usable[0];
            return [
                'interface' => $chosen,
                'address' => $chosen->getPreferredIpv4() ?? $chosen->addresses[0],
            ];
        }

        // If multiple usable interfaces and interactive prompt provided
        if ($promptCallback !== null) {
            $options = [];
            foreach ($usable as $index => $iface) {
                $ip = $iface->getPreferredIpv4()?->ip ?? 'unknown';
                $type = $iface->type->label();
                $options[$index + 1] = "{$type} ({$iface->name}): {$ip}";
            }

            $selectedIdx = (int) $promptCallback($options, 1);
            $chosenIndex = max(0, min(count($usable) - 1, $selectedIdx - 1));
            $chosen = $usable[$chosenIndex];

            return [
                'interface' => $chosen,
                'address' => $chosen->getPreferredIpv4() ?? $chosen->addresses[0],
            ];
        }

        // Deterministic highest priority interface for non-interactive
        $chosen = $usable[0];

        return [
            'interface' => $chosen,
            'address' => $chosen->getPreferredIpv4() ?? $chosen->addresses[0],
        ];
    }
}
