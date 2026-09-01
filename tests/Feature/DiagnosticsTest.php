<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Feature;

use Mrokwor\LaravelLan\Tests\TestCase;

final class DiagnosticsTest extends TestCase
{
    public function test_runs_diagnostics_command_successfully(): void
    {
        $this->artisan('lan --diagnose')
            ->expectsOutputToContain('Laravel LAN Diagnostics')
            ->expectsOutputToContain('Host Binding')
            ->expectsOutputToContain('LAN Network Interface')
            ->expectsOutputToContain('Port Availability')
            ->assertExitCode(0);
    }

    public function test_runs_diagnostics_with_json_output(): void
    {
        $this->artisan('lan --diagnose --json')
            ->assertSuccessful()
            ->expectsOutputToContain('"diagnostics": [');
    }
}
