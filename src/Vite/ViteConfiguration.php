<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Vite;

final readonly class ViteConfiguration
{
    public function __construct(
        public bool $enabled = true,
        public int $port = 5173,
        public bool $isConfiguredForLan = false,
    ) {
    }

    /**
     * Recommended Vite server config snippet for users to enable HMR on LAN devices.
     */
    public static function getSnippet(string $lanIp, int $port = 5173): string
    {
        return <<<JS
// In vite.config.js:
export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: {
            host: '{$lanIp}',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
JS;
    }
}
