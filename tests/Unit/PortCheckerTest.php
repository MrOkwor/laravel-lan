<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use InvalidArgumentException;
use Mrokwor\LaravelLan\Network\PortChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PortCheckerTest extends TestCase
{
    public function test_checks_valid_free_port(): void
    {
        $checker = new PortChecker();
        // Typically a high port like 59123 is free on test environments
        $isAvailable = $checker->isPortAvailable(59123);
        $this->assertTrue($isAvailable);
    }

    public function test_rejects_invalid_port_numbers(): void
    {
        $checker = new PortChecker();

        $this->expectException(InvalidArgumentException::class);
        $checker->isPortAvailable(70000);
    }

    public function test_resolves_port_when_available(): void
    {
        $checker = new PortChecker();
        $port = $checker->resolvePort(59124);
        $this->assertSame(59124, $port);
    }

    public function test_fails_when_port_occupied_and_auto_port_disabled(): void
    {
        // Start a mock listening server on a free port
        $socket = stream_socket_server('tcp://127.0.0.1:59125', $errNo, $errStr);
        $this->assertIsResource($socket);

        $checker = new PortChecker();

        try {
            $checker->resolvePort(59125, autoPort: false);
            $this->fail('Expected RuntimeException when port is occupied.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Port 59125 is already in use', $e->getMessage());
        } finally {
            fclose($socket);
        }
    }

    public function test_finds_next_port_when_auto_port_enabled(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:59126', $errNo, $errStr);
        $this->assertIsResource($socket);

        $checker = new PortChecker();

        try {
            $resolved = $checker->resolvePort(59126, autoPort: true, min: 59126, max: 59130);
            $this->assertGreaterThan(59126, $resolved);
        } finally {
            fclose($socket);
        }
    }
}
