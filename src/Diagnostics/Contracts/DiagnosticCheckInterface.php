<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Contracts;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;

interface DiagnosticCheckInterface
{
    /**
     * Run the diagnostic check against the given LAN configuration.
     */
    public function check(LanConfiguration $config): DiagnosticResult;
}
