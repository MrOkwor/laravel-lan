<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;
use Mrokwor\LaravelLan\Support\Platform;

final class FirewallHintCheck implements DiagnosticCheckInterface
{
    public function check(LanConfiguration $config): DiagnosticResult
    {
        $port = $config->port;

        if (Platform::isWindows()) {
            return DiagnosticResult::info(
                name: 'Firewall & Network',
                message: "Ensure Windows Defender Firewall allows incoming connections for PHP on port {$port}.",
                hint: 'If connecting fails from your phone, check if Wi-Fi network profile is set to "Private" and Client Isolation is off.'
            );
        }

        if (Platform::isMac()) {
            return DiagnosticResult::info(
                name: 'Firewall & Network',
                message: "Ensure macOS Application Firewall allows incoming connections to PHP on port {$port}.",
                hint: 'Make sure your phone and Mac are connected to the exact same Wi-Fi SSID.'
            );
        }

        return DiagnosticResult::info(
            name: 'Firewall & Network',
            message: "Ensure iptables/ufw allows inbound TCP connections on port {$port}.",
            hint: 'E.g., `sudo ufw allow ' . $port . '/tcp` if UFW is active.'
        );
    }
}
