<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;
use Mrokwor\LaravelLan\Network\PortChecker;
use Throwable;

final class PortCheck implements DiagnosticCheckInterface
{
    public function __construct(
        private ?PortChecker $portChecker = null
    ) {
    }

    public function check(LanConfiguration $config): DiagnosticResult
    {
        $portChecker = $this->portChecker ?? new PortChecker();
        $port = $config->port;

        try {
            $isAvailable = $portChecker->isPortAvailable($port, $config->host);

            if ($isAvailable) {
                return DiagnosticResult::pass(
                    name: 'Port Availability',
                    message: "Port {$port} is available for binding on {$config->host}.",
                    data: ['port' => $port, 'host' => $config->host]
                );
            }

            if ($config->autoPort) {
                try {
                    $nextPort = $portChecker->resolvePort(
                        preferredPort: $port,
                        autoPort: true,
                        min: $config->autoPortMin,
                        max: $config->autoPortMax,
                        host: $config->host
                    );

                    return DiagnosticResult::pass(
                        name: 'Port Availability',
                        message: "Default port {$port} is in use; auto-port will automatically bind to port {$nextPort}.",
                        data: ['preferred_port' => $port, 'resolved_port' => $nextPort, 'host' => $config->host]
                    );
                } catch (Throwable) {
                }
            }

            $nextPort = $port + 1;
            return DiagnosticResult::fail(
                name: 'Port Availability',
                message: "Port {$port} is already in use by another process.",
                hint: "Run with an available port using: php artisan lan --port={$nextPort}",
                data: ['port' => $port, 'host' => $config->host]
            );
        } catch (Throwable $e) {
            return DiagnosticResult::fail(
                name: 'Port Availability',
                message: "Unable to verify port {$port}: " . $e->getMessage(),
                data: ['port' => $port]
            );
        }
    }
}
