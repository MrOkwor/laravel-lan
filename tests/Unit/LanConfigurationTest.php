<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use PHPUnit\Framework\TestCase;

final class LanConfigurationTest extends TestCase
{
    public function test_resolves_defaults(): void
    {
        $config = LanConfiguration::resolve([], []);

        $this->assertSame('0.0.0.0', $config->host);
        $this->assertSame(8000, $config->port);
        $this->assertNull($config->interface);
        $this->assertTrue($config->autoPort);
        $this->assertTrue($config->qr);
        $this->assertTrue($config->viteEnabled);
        $this->assertFalse($config->https);
        $this->assertFalse($config->diagnose);
        $this->assertFalse($config->json);
        $this->assertFalse($config->force);
    }

    public function test_cli_options_override_defaults(): void
    {
        $config = LanConfiguration::resolve([
            'host' => '192.168.1.10',
            'port' => '8080',
            'interface' => 'en0',
            'no-qr' => true,
            'no-vite' => true,
            'https' => true,
            'diagnose' => true,
            'json' => true,
            'force' => true,
        ], [
            'port' => 9000,
        ]);

        $this->assertSame('192.168.1.10', $config->host);
        $this->assertSame(8080, $config->port);
        $this->assertSame('en0', $config->interface);
        $this->assertFalse($config->qr);
        $this->assertFalse($config->viteEnabled);
        $this->assertTrue($config->https);
        $this->assertTrue($config->diagnose);
        $this->assertTrue($config->json);
        $this->assertTrue($config->force);
    }

    public function test_config_array_overrides_defaults_when_cli_omitted(): void
    {
        $config = LanConfiguration::resolve([], [
            'port' => 8888,
            'interface' => 'wlan0',
            'qr' => false,
        ]);

        $this->assertSame(8888, $config->port);
        $this->assertSame('wlan0', $config->interface);
        $this->assertFalse($config->qr);
    }
}
