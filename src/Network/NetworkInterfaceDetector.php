<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\Detectors\LinuxDetector;
use Mrokwor\LaravelLan\Network\Detectors\MacOsDetector;
use Mrokwor\LaravelLan\Network\Detectors\PhpNativeDetector;
use Mrokwor\LaravelLan\Network\Detectors\WindowsDetector;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Support\Platform;

final class NetworkInterfaceDetector implements InterfaceDetectorInterface
{
    /**
     * @param array<InterfaceDetectorInterface>|null $detectors
     */
    public function __construct(
        private ?array $detectors = null
    ) {
    }

    /**
     * Detect and return all network interfaces, sorted by highest priority.
     *
     * @return array<NetworkInterface>
     */
    public function detect(): array
    {
        $detectors = $this->detectors ?? $this->getDefaultDetectors();
        $allInterfaces = [];

        foreach ($detectors as $detector) {
            $interfaces = $detector->detect();
            if (!empty($interfaces)) {
                $allInterfaces = array_merge($allInterfaces, $interfaces);
                // If primary OS-specific detector returned valid interfaces, we prefer its results
                if (!$detector instanceof PhpNativeDetector) {
                    break;
                }
            }
        }

        // Deduplicate and rank interfaces
        $unique = $this->deduplicate($allInterfaces);
        $ranked = array_map(fn (NetworkInterface $iface) => $this->withPriority($iface), $unique);

        // Sort descending by priority
        usort($ranked, fn (NetworkInterface $a, NetworkInterface $b) => $b->priority <=> $a->priority);

        return $ranked;
    }

    /**
     * Get only the usable LAN interfaces.
     *
     * @return array<NetworkInterface>
     */
    public function detectUsableLanInterfaces(): array
    {
        return array_values(array_filter(
            $this->detect(),
            fn (NetworkInterface $iface) => $iface->isUsableLan()
        ));
    }

    /**
     * @return array<InterfaceDetectorInterface>
     */
    private function getDefaultDetectors(): array
    {
        $detectors = [];

        if (Platform::isWindows()) {
            $detectors[] = new WindowsDetector();
        } elseif (Platform::isMac()) {
            $detectors[] = new MacOsDetector();
        } elseif (Platform::isLinux()) {
            $detectors[] = new LinuxDetector();
        }

        $detectors[] = new PhpNativeDetector();

        return $detectors;
    }

    /**
     * @param array<NetworkInterface> $interfaces
     * @return array<NetworkInterface>
     */
    private function deduplicate(array $interfaces): array
    {
        $unique = [];
        foreach ($interfaces as $iface) {
            $key = strtolower($iface->name);
            if (!isset($unique[$key])) {
                $unique[$key] = $iface;
            } else {
                // If existing has no addresses but new one does, prefer the one with addresses
                if (empty($unique[$key]->addresses) && !empty($iface->addresses)) {
                    $unique[$key] = $iface;
                }
            }
        }

        return array_values($unique);
    }

    private function withPriority(NetworkInterface $iface): NetworkInterface
    {
        $priority = 0;

        if (!$iface->isUp) {
            $priority -= 200;
        }

        if ($iface->isLoopback) {
            $priority -= 100;
        }

        if ($iface->isVirtual) {
            $priority -= 50;
        }

        if ($iface->hasPrivateIpv4()) {
            $priority += 50;

            if ($iface->type === InterfaceType::Wifi || $iface->isWireless) {
                $priority += 50;
            } elseif ($iface->type === InterfaceType::Ethernet) {
                $priority += 40;
            } elseif ($iface->type === InterfaceType::Vpn) {
                $priority += 10;
            } else {
                $priority += 20;
            }
        }

        return new NetworkInterface(
            name: $iface->name,
            displayName: $iface->displayName,
            type: $iface->type,
            addresses: $iface->addresses,
            isUp: $iface->isUp,
            isWireless: $iface->isWireless,
            isVirtual: $iface->isVirtual,
            isLoopback: $iface->isLoopback,
            priority: $priority,
            macAddress: $iface->macAddress,
        );
    }
}
