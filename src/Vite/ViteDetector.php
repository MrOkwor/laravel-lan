<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Vite;

final class ViteDetector
{
    public function __construct(
        private ?string $basePath = null
    ) {
    }

    /**
     * Check if Vite is configured in the current project.
     */
    public function isViteProject(): bool
    {
        $base = $this->getBasePath();

        $configFiles = [
            'vite.config.js',
            'vite.config.ts',
            'vite.config.mjs',
            'vite.config.cjs',
        ];

        foreach ($configFiles as $file) {
            if (file_exists("{$base}/{$file}")) {
                return true;
            }
        }

        $packageJson = "{$base}/package.json";
        if (file_exists($packageJson)) {
            $content = @file_get_contents($packageJson);
            if ($content && (str_contains($content, '"vite"') || str_contains($content, '"laravel-vite-plugin"'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the Vite configuration file includes server.host setting.
     */
    public function hasLanHostConfigured(): bool
    {
        $base = $this->getBasePath();

        $configFiles = [
            'vite.config.js',
            'vite.config.ts',
            'vite.config.mjs',
            'vite.config.cjs',
        ];

        foreach ($configFiles as $file) {
            $path = "{$base}/{$file}";
            if (file_exists($path)) {
                $content = @file_get_contents($path);
                if ($content && (
                    preg_match('/host\s*:\s*(true|\'0\.0\.0\.0\'|"0\.0\.0\.0"|true)/i', $content) ||
                    str_contains($content, 'server:') && str_contains($content, 'host:')
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getBasePath(): string
    {
        return $this->basePath ?? (function_exists('base_path') ? base_path() : getcwd());
    }
}
