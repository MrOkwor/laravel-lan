<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Throwable;

final class NetworkInterfaceCheck implements DiagnosticCheckInterface
{
    public function __construct(
        private ?NetworkInterfaceDetector $detector = null
    ) {
    }

    public function check(LanConfiguration $config): DiagnosticResult
    {
        $detector = $this->detector ?? new NetworkInterfaceDetector();

        try {
            $all = $detector->detect();
            $usable = $detector->detectUsableLanInterfaces();

            if (empty($usable)) {
                return DiagnosticResult::fail(
                    name: 'LAN Network Interface',
                    message: 'No active private LAN network interfaces detected.',
                    hint: 'Please connect this computer to a local Wi-Fi or Ethernet network.',
                    data: ['interfaces' => array_map(fn ($i) => $i->name, $all)]
                );
            }

            $summary = [];
            foreach ($usable as $iface) {
                $ip = $iface->getPreferredIpv4()?->ip ?? 'unknown';
                $summary[] = "{$iface->displayName} ({$ip})";
            }

            return DiagnosticResult::pass(
                name: 'LAN Network Interface',
                message: 'Active LAN interface(s) available: ' . implode(', ', $summary),
                data: ['usable' => $summary]
            );
        } catch (Throwable $e) {
            return DiagnosticResult::fail(
                name: 'LAN Network Interface',
                message: 'Failed to detect network interfaces: ' . $e->getMessage(),
                hint: 'Check your operating system network permissions.'
            );
        }
    }
}
