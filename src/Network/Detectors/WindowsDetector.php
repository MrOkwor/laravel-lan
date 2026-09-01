<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Detectors;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;
use Mrokwor\LaravelLan\Network\Enums\InterfaceType;
use Mrokwor\LaravelLan\Network\NetworkAddress;
use Mrokwor\LaravelLan\Network\NetworkInterface;
use Throwable;

final class WindowsDetector implements InterfaceDetectorInterface
{
    public function __construct(
        private ?string $rawOutput = null
    ) {
    }

    public function detect(): array
    {
        $output = $this->rawOutput ?? $this->runIpconfig();
        if (empty(trim($output))) {
            return [];
        }

        return $this->parse($output);
    }

    private function runIpconfig(): string
    {
        $output = @shell_exec('ipconfig /all 2>&1');
        return is_string($output) ? $output : '';
    }

    /**
     * Parse ipconfig /all output.
     *
     * @return array<NetworkInterface>
     */
    public function parse(string $output): array
    {
        $interfaces = [];
        $blocks = preg_split('/\r?\n(?=[A-Za-z0-9].*adapter )/', $output);

        if (!$blocks) {
            return [];
        }

        foreach ($blocks as $block) {
            if (!preg_match('/(?:Wireless LAN|Ethernet|Unknown)?\s*adapter\s+([^:]+):/i', $block, $matchHeader)) {
                continue;
            }

            $adapterName = trim($matchHeader[1]);
            $isWireless = stripos($block, 'Wireless') !== false || stripos($adapterName, 'Wi-Fi') !== false;

            $description = '';
            if (preg_match('/Description[\s\.]+:\s*(.+)/i', $block, $matchDesc)) {
                $description = trim($matchDesc[1]);
            }

            $displayName = $description !== '' ? "{$adapterName} ({$description})" : $adapterName;

            $mac = null;
            if (preg_match('/Physical Address[\s\.]+:\s*([0-9A-Fa-f\-]+)/i', $block, $matchMac)) {
                $mac = trim($matchMac[1]);
            }

            $isMediaDisconnected = preg_match('/Media State[\s\.]+:\s*Media disconnected/i', $block) === 1;
            $isUp = !$isMediaDisconnected;

            $lowerCombined = strtolower($adapterName . ' ' . $description);
            $isVirtual = str_contains($lowerCombined, 'vethernet')
                || str_contains($lowerCombined, 'wsl')
                || str_contains($lowerCombined, 'virtualbox host-only')
                || str_contains($lowerCombined, 'vmware virtual')
                || str_contains($lowerCombined, 'docker')
                || str_contains($lowerCombined, 'tailscale')
                || str_contains($lowerCombined, 'wireguard')
                || str_contains($lowerCombined, 'loopback')
                || str_contains($lowerCombined, 'npcap');

            $isVpn = str_contains($lowerCombined, 'tap-windows')
                || str_contains($lowerCombined, 'vpn')
                || str_contains($lowerCombined, 'wireguard')
                || str_contains($lowerCombined, 'tailscale')
                || str_contains($lowerCombined, 'nordlynx')
                || str_contains($lowerCombined, 'openvpn');

            $isLoopback = str_contains($lowerCombined, 'loopback');

            $type = match (true) {
                $isLoopback => InterfaceType::Loopback,
                $isVpn => InterfaceType::Vpn,
                $isWireless => InterfaceType::Wifi,
                $isVirtual => InterfaceType::Virtual,
                str_contains($lowerCombined, 'ethernet') => InterfaceType::Ethernet,
                default => InterfaceType::Other,
            };

            $addresses = [];

            // Match IPv4 addresses
            if (preg_match_all('/IPv4 Address[\s\.]+:\s*([0-9\.]+)(?:\(Preferred\))?/i', $block, $ipv4Matches)) {
                foreach ($ipv4Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv4);
                    } catch (Throwable) {
                    }
                }
            }

            // Match IPv6 addresses
            if (preg_match_all('/(?:IPv6 Address|Link-local IPv6 Address)[\s\.]+:\s*([0-9a-fA-F:]+)(?:%[0-9]+)?(?:\(Preferred\))?/i', $block, $ipv6Matches)) {
                foreach ($ipv6Matches[1] as $ip) {
                    try {
                        $addresses[] = new NetworkAddress($ip, AddressFamily::IPv6);
                    } catch (Throwable) {
                    }
                }
            }

            $interfaces[] = new NetworkInterface(
                name: $adapterName,
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
