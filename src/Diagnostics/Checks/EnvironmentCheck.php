<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;

final class EnvironmentCheck implements DiagnosticCheckInterface
{
    public function check(LanConfiguration $config): DiagnosticResult
    {
        $env = function_exists('app') && app()->bound('env')
            ? app()->environment()
            : (getenv('APP_ENV') ?: 'local');

        if (strtolower($env) === 'production') {
            if ($config->blockProduction && !$config->force) {
                return DiagnosticResult::fail(
                    name: 'Environment Safety',
                    message: "APP_ENV is set to 'production'. Laravel LAN is intended for local development only.",
                    hint: 'If you really intend to run in this environment, provide the --force flag.',
                    data: ['env' => $env]
                );
            }

            return DiagnosticResult::warning(
                name: 'Environment Safety',
                message: "Running with APP_ENV='production' because --force was supplied.",
                hint: 'Be cautious when exposing production servers.',
                data: ['env' => $env]
            );
        }

        return DiagnosticResult::pass(
            name: 'Environment Safety',
            message: "Application environment is '{$env}'.",
            data: ['env' => $env]
        );
    }
}
