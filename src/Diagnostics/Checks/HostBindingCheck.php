<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;

final class HostBindingCheck implements DiagnosticCheckInterface
{
    public function check(LanConfiguration $config): DiagnosticResult
    {
        $host = $config->host;

        if ($host === '127.0.0.1' || $host === 'localhost') {
            return DiagnosticResult::warning(
                name: 'Host Binding',
                message: "Server host is explicitly bound to '{$host}'.",
                hint: "Binding to localhost prevents other devices on your LAN from accessing the app. Use --host=0.0.0.0 instead.",
                data: ['host' => $host]
            );
        }

        if ($host === '0.0.0.0') {
            return DiagnosticResult::pass(
                name: 'Host Binding',
                message: "Server host is bound to '0.0.0.0' (all available network interfaces).",
                data: ['host' => $host]
            );
        }

        return DiagnosticResult::pass(
            name: 'Host Binding',
            message: "Server host is bound to '{$host}'.",
            data: ['host' => $host]
        );
    }
}
