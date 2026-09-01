<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Detectors;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Throwable;

final class LinuxDetector implements InterfaceDetectorInterface
{
    public function __construct(
        private ?string $rawJsonOutput = null,
        private ?string $rawTextOutput = null,
    ) {
    }

    public function detect(): array
    {
        if ($this->rawJsonOutput !== null) {
            $parsed = $this->parseJson($this->rawJsonOutput);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        if ($this->rawTextOutput !== null) {
            return $this->parseText($this->rawTextOutput);
        }

        // Try `ip -j addr`
        $json = $this->runCommand('ip -j addr 2>/dev/null');
        if (!empty(trim($json))) {
            $parsed = $this->parseJson($json);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        // Fallback to `ip addr`
        $text = $this->runCommand('ip addr 2>/dev/null');
        if (!empty(trim($text))) {
            return $this->parseText($text);
        }

        // Fallback to `ifconfig`
        $ifconfig = $this->runCommand('ifconfig -a 2>/dev/null');
        if (!empty(trim($ifconfig))) {
            return $this->parseIfconfig($ifconfig);
        }

        return [];
    }

    private function runCommand(string $cmd): string
    {
        $output = @shell_exec($cmd);
        return is_string($output) ? $output : '';
    }

    /**
     * Parse `ip -j addr` output.
     *
     * @return array<NetworkInterface>
     */
    public function parseJson(string $json): array
    {
        $data = @json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $interfaces = [];
        foreach ($data as $item) {
            $name = (string) ($item['ifname'] ?? '');
            if ($name === '') {
                continue;
            }

            $flags = $item['flags'] ?? [];
            $operState = strtoupper((string) ($item['operstate'] ?? ''));
            $isUp = in_array('UP', $flags, true) || $operState === 'UP' || $operState === 'UNKNOWN';
            $isLoopback = in_array('LOOPBACK', $flags, true) || $name === 'lo';

            $mac = $item['address'] ?? null;

            $lower = strtolower($name);
            $isWireless = str_starts_with($lower, 'wl') || str_contains($lower, 'wifi') || str_contains($lower, 'wlan');
            $isVirtual = str_starts_with($lower, 'docker')
                || str_starts_with($lower, 'br-')
                || str_starts_with($lower, 'veth')
                || str_starts_with($lower, 'virbr')
                || str_starts_with($lower, 'tailscale')
                || str_starts_with($lower, 'wg')
                || str_starts_with($lower, 'zt');

            $isVpn = str_starts_with($lower, 'tun')
                || str_starts_with($lower, 'tap')
                || str_starts_with($lower, 'tailscale')
                || str_starts_with($lower, 'wg');

            $type = match (true) {
                $isLoopback => InterfaceType::Loopback,
                $isVpn => InterfaceType::Vpn,
                $isWireless => InterfaceType::Wifi,
                $isVirtual => InterfaceType::Virtual,
                str_starts_with($lower, 'eth') || str_starts_with($lower, 'en') => InterfaceType::Ethernet,
                default => InterfaceType::Other,
            };

            $addresses = [];
            foreach ($item['addr_info'] ?? [] as $addr) {
                $ip = $addr['local'] ?? null;
                if (!$ip || !is_string($ip)) {
                    continue;
                }

                $family = match ($addr['family'] ?? null) {
                    'inet' => AddressFamily::IPv4,
                    'inet6' => AddressFamily::IPv6,
                    default => null,
                };

                try {
                    $addresses[] = new NetworkAddress($ip, $family);
                } catch (Throwable) {
                }
            }

            $interfaces[] = new NetworkInterface(
                name: $name,
                displayName: $name,
                type: $type,
                addresses: $addresses,
                isUp: $isUp,
                isWireless: $isWireless,
                isVirtual: $isVirtual,
                isLoopback: $isLoopback,
                macAddress: $mac ? (string) $mac : null,
            );
        }

        return $interfaces;
    }

    /**
     * Parse standard `ip addr` text output.
     *
     * @return array<NetworkInterface>
     */
    public function parseText(string $output): array
    {
        $interfaces = [];
        $blocks = preg_split('/\r?\n(?=[0-9]+:\s+)/', trim($output));

        if (!$blocks) {
            return [];
        }

        foreach ($blocks as $block) {
            if (!preg_match('/^[0-9]+:\s+([^:@]+)[@:]\s+<([^>]+)>/m', $block, $headerMatch)) {
                continue;
            }

            $name = trim($headerMatch[1]);
            $flags = explode(',', strtoupper($headerMatch[2]));
            $isUp = in_array('UP', $flags, true);
            $isLoopback = in_array('LOOPBACK', $flags, true) || $name === 'lo';

            $mac = null;
            if (preg_match('/link\/ether\s+([0-9a-fA-F:]+)/i', $block, $macMatch)) {
                $mac = trim($macMatch[1]);
            }

            $lower = strtolower($name);
            $isWireless = str_starts_with($lower, 'wl') || str_contains($lower, 'wifi') || str_contains($lower, 'wlan');
            $isVirtual = str_starts_with($lower, 'docker')
                || str_starts_with($lower, 'br-')
                || str_starts_with($lower, 'veth')
                || str_starts_with($lower, 'virbr')
                || str_starts_with($lower, 'tailscale')
                || str_starts_with($lower, 'wg');

            $isVpn = str_starts_with($lower, 'tun') || str_starts_with($lower, 'tap') || str_starts_with($lower, 'wg');

            $type = match (true) {
                $isLoopback => InterfaceType::Loopback,
                $isVpn => InterfaceType::Vpn,
                $isWireless => InterfaceType::Wifi,
                $isVirtual => InterfaceType::Virtual,
                str_starts_with($lower, 'eth') || str_starts_with($lower, 'en') => InterfaceType::Ethernet,
                default => InterfaceType::Other,
            };

            $addresses = [];

            // inet
            if (preg_match_all('/inet\s+([0-9\.]+)\//i', $block, $ipv4Matches)) {
                foreach ($ipv4Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv4);
                    } catch (Throwable) {
                    }
                }
            }

            // inet6
            if (preg_match_all('/inet6\s+([0-9a-fA-F:]+)\//i', $block, $ipv6Matches)) {
                foreach ($ipv6Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv6);
                    } catch (Throwable) {
                    }
                }
            }

            $interfaces[] = new NetworkInterface(
                name: $name,
                displayName: $name,
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

    /**
     * Fallback Linux ifconfig parser.
     *
     * @return array<NetworkInterface>
     */
    public function parseIfconfig(string $output): array
    {
        $detector = new MacOsDetector($output);
        return $detector->parseIfconfig($output);
    }
}
