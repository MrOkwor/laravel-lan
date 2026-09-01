<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Detectors;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Throwable;

final class MacOsDetector implements InterfaceDetectorInterface
{
    public function __construct(
        private ?string $rawIfconfig = null,
        private ?string $rawNetworkSetup = null,
    ) {
    }

    public function detect(): array
    {
        $ifconfigOutput = $this->rawIfconfig ?? $this->runCommand('ifconfig 2>/dev/null');
        if (empty(trim($ifconfigOutput))) {
            return [];
        }

        $portMap = $this->parseHardwarePorts($this->rawNetworkSetup ?? $this->runCommand('networksetup -listallhardwareports 2>/dev/null'));

        return $this->parseIfconfig($ifconfigOutput, $portMap);
    }

    private function runCommand(string $cmd): string
    {
        $output = @shell_exec($cmd);
        return is_string($output) ? $output : '';
    }

    /**
     * Parse `networksetup -listallhardwareports` to map device (e.g. en0) to name (e.g. Wi-Fi).
     *
     * @return array<string, string>
     */
    public function parseHardwarePorts(string $output): array
    {
        $map = [];
        if (empty(trim($output))) {
            return $map;
        }

        $blocks = preg_split('/\r?\n\r?\n/', trim($output));
        if (!$blocks) {
            return $map;
        }

        foreach ($blocks as $block) {
            if (preg_match('/Hardware Port:\s*(.+)/i', $block, $matchPort) &&
                preg_match('/Device:\s*(.+)/i', $block, $matchDev)) {
                $port = trim($matchPort[1]);
                $device = trim($matchDev[1]);
                $map[$device] = $port;
            }
        }

        return $map;
    }

    /**
     * Parse `ifconfig` output for macOS.
     *
     * @param array<string, string> $portMap
     * @return array<NetworkInterface>
     */
    public function parseIfconfig(string $output, array $portMap = []): array
    {
        $interfaces = [];
        $blocks = preg_split('/\r?\n(?=[a-zA-Z0-9_-]+:)/', trim($output));

        if (!$blocks) {
            return [];
        }

        foreach ($blocks as $block) {
            if (!preg_match('/^([a-zA-Z0-9_-]+):\s*flags=[0-9]+<([^>]+)>/m', $block, $headerMatch)) {
                continue;
            }

            $name = trim($headerMatch[1]);
            $flags = explode(',', strtoupper($headerMatch[2]));
            $isUp = in_array('UP', $flags, true);
            $isLoopback = in_array('LOOPBACK', $flags, true) || $name === 'lo0';

            // Check status: active
            $hasStatus = preg_match('/status:\s*(.+)/i', $block, $statusMatch);
            if ($hasStatus) {
                $status = strtolower(trim($statusMatch[1]));
                if ($status === 'inactive') {
                    $isUp = false;
                }
            }

            $mac = null;
            if (preg_match('/ether\s+([0-9a-fA-F:]+)/i', $block, $macMatch)) {
                $mac = trim($macMatch[1]);
            }

            $hardwareName = $portMap[$name] ?? null;
            $displayName = $hardwareName ? "{$name} ({$hardwareName})" : $name;

            $lowerCombined = strtolower($name . ' ' . ($hardwareName ?? ''));
            $isWireless = str_contains($lowerCombined, 'wi-fi') || str_contains($lowerCombined, 'airdrop') || str_contains($lowerCombined, 'awdl');
            $isVpn = str_starts_with($name, 'utun') || str_starts_with($name, 'ppp') || str_contains($lowerCombined, 'vpn');
            $isVirtual = str_starts_with($name, 'bridge')
                || str_starts_with($name, 'awdl')
                || str_starts_with($name, 'llw')
                || str_starts_with($name, 'gif')
                || str_starts_with($name, 'stf')
                || str_starts_with($name, 'vmenet')
                || str_contains($lowerCombined, 'docker')
                || str_contains($lowerCombined, 'tailscale');

            $type = match (true) {
                $isLoopback => InterfaceType::Loopback,
                $isVpn => InterfaceType::Vpn,
                $isWireless => InterfaceType::Wifi,
                $isVirtual => InterfaceType::Virtual,
                str_contains($lowerCombined, 'ethernet') => InterfaceType::Ethernet,
                default => InterfaceType::Other,
            };

            $addresses = [];

            // Match inet
            if (preg_match_all('/inet\s+([0-9\.]+)\s+(?:netmask|-->)/i', $block, $ipv4Matches)) {
                foreach ($ipv4Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv4);
                    } catch (Throwable) {
                    }
                }
            }

            // Match inet6
            if (preg_match_all('/inet6\s+([0-9a-fA-F:]+)(?:%[a-zA-Z0-9_-]+)?\s+prefixlen/i', $block, $ipv6Matches)) {
                foreach ($ipv6Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv6);
                    } catch (Throwable) {
                    }
                }
            }

            $interfaces[] = new NetworkInterface(
                name: $name,
                displayName: $displayName,
                type: $type,
                addresses: $addresses,
                isUp: $isUp,
                isWireless: $isWireless,
                isVirtual: $isVirtual,
                isLoopback: $isLoopback,
                macAddress: $mac,
            );
        }

        return $interfaces;
    }
}
