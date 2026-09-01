<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

use InvalidArgumentException;
use RuntimeException;

final class PortChecker
{
    /**
     * Check whether a specific port is available for binding.
     */
    public function isPortAvailable(int $port, string $host = '0.0.0.0'): bool
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Port must be between 1 and 65535. Given: {$port}");
        }

        // Test 1: Check if an existing service is already accepting connections on localhost
        $conn = @fsockopen('127.0.0.1', $port, $errNo, $errStr, 0.05);
        if (is_resource($conn)) {
            fclose($conn);
            return false;
        }

        // Test 2: Check if an existing service is accepting connections on specific host
        if ($host !== '0.0.0.0' && $host !== '127.0.0.1' && filter_var($host, FILTER_VALIDATE_IP)) {
            $hostConn = @fsockopen($host, $port, $errNo, $errStr, 0.05);
            if (is_resource($hostConn)) {
                fclose($hostConn);
                return false;
            }
        }

        // Test 3: Attempt binding on localhost
        $server = @stream_socket_server(
            "tcp://127.0.0.1:{$port}",
            $errNo,
            $errStr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        if (!is_resource($server)) {
            return false;
        }

        fclose($server);

        // Test 4: If binding to a non-localhost host, test that bind as well
        if ($host !== '0.0.0.0' && $host !== '127.0.0.1') {
            $specificServer = @stream_socket_server(
                "tcp://{$host}:{$port}",
                $errNo,
                $errStr,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            );

            if (!is_resource($specificServer)) {
                return false;
            }

            fclose($specificServer);
        }

        return true;
    }

    /**
     * Resolve an available port. If preferred port is free, returns it.
     * If occupied and auto-port is enabled, searches within range.
     * Otherwise throws a clear RuntimeException.
     */
    public function resolvePort(int $preferredPort, bool $autoPort = false, int $min = 8000, int $max = 8100, string $host = '0.0.0.0'): int
    {
        if ($this->isPortAvailable($preferredPort, $host)) {
            return $preferredPort;
        }

        if (!$autoPort) {
            $nextPort = $preferredPort + 1;
            throw new RuntimeException(
                "Port {$preferredPort} is already in use.\n" .
                "Try running with a different port:\n" .
                "    php artisan lan --port={$nextPort}"
            );
        }

        for ($port = max($min, 1); $port <= min($max, 65535); $port++) {
            if ($this->isPortAvailable($port, $host)) {
                return $port;
            }
        }

        throw new RuntimeException(
            "Could not find an available port in the range {$min}-{$max}."
        );
    }
}
