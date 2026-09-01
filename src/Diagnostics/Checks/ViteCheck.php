<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Checks;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticResult;
use Mrokwor\LaravelLan\Vite\ViteConfiguration;
use Mrokwor\LaravelLan\Vite\ViteDetector;

final class ViteCheck implements DiagnosticCheckInterface
{
    public function __construct(
        private ?ViteDetector $viteDetector = null
    ) {
    }

    public function check(LanConfiguration $config): DiagnosticResult
    {
        if (!$config->viteEnabled) {
            return DiagnosticResult::info(
                name: 'Vite Integration',
                message: 'Vite integration is disabled in configuration.'
            );
        }

        $detector = $this->viteDetector ?? new ViteDetector();

        if (!$detector->isViteProject()) {
            return DiagnosticResult::info(
                name: 'Vite Integration',
                message: 'No Vite configuration detected in the project.'
            );
        }

        if ($detector->hasLanHostConfigured()) {
            return DiagnosticResult::pass(
                name: 'Vite Integration',
                message: 'Vite is configured to listen on 0.0.0.0 for LAN access.'
            );
        }

        return DiagnosticResult::warning(
            name: 'Vite Integration',
            message: 'Vite detected, but server.host may be bound only to localhost.',
            hint: "To ensure CSS/JS HMR works on your mobile device, add `server: { host: '0.0.0.0' }` to vite.config.js."
        );
    }
}
