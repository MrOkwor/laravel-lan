<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Config;

final readonly class LanConfiguration
{
    public function __construct(
        public string $host = '0.0.0.0',
        public int $port = 8000,
        public ?string $interface = null,
        public bool $autoPort = true,
        public int $autoPortMin = 8000,
        public int $autoPortMax = 8100,
        public bool $qr = true,
        public bool $viteEnabled = true,
        public int $vitePort = 5173,
        public bool $https = false,
        public bool $allowPublicBind = false,
        public bool $blockProduction = true,
        public bool $diagnose = false,
        public bool $json = false,
        public bool $force = false,
    ) {
    }

    /**
     * Resolve configuration from CLI options, Laravel config, and defaults.
     * Precedence: CLI > Environment > Config > Default
     *
     * @param array<string, mixed> $cliOptions
     * @param array<string, mixed> $configArray
     */
    public static function resolve(array $cliOptions = [], array $configArray = []): self
    {
        // 1. Host
        $host = (string) ($cliOptions['host']
            ?? getenv('LARAVEL_LAN_HOST')
            ?: ($configArray['host'] ?? '0.0.0.0'));

        // 2. Port
        $cliPort = $cliOptions['port'] ?? null;
        $port = $cliPort !== null && is_numeric($cliPort)
            ? (int) $cliPort
            : (int) (getenv('LARAVEL_LAN_PORT') ?: ($configArray['port'] ?? 8000));

        // 3. Interface
        $interface = (string) ($cliOptions['interface']
            ?? getenv('LARAVEL_LAN_INTERFACE')
            ?: ($configArray['interface'] ?? ''));
        $interface = trim($interface) !== '' ? trim($interface) : null;

        // 4. Auto Port
        $noAutoPort = (bool) ($cliOptions['no-auto-port'] ?? false);
        $autoPortEnv = getenv('LARAVEL_LAN_AUTO_PORT');
        $autoPortConfig = $configArray['auto_port'] ?? true;
        $autoPort = !$noAutoPort && ($autoPortEnv !== false ? filter_var($autoPortEnv, FILTER_VALIDATE_BOOLEAN) : (bool) $autoPortConfig);
        $autoPortMin = (int) ($configArray['auto_port_range']['min'] ?? 8000);
        $autoPortMax = (int) ($configArray['auto_port_range']['max'] ?? 8100);

        // 5. QR Code
        $noQr = (bool) ($cliOptions['no-qr'] ?? false);
        $qrConfig = (bool) (getenv('LARAVEL_LAN_QR') !== false ? getenv('LARAVEL_LAN_QR') : ($configArray['qr'] ?? true));
        $qr = !$noQr && $qrConfig;

        // 6. Vite
        $noVite = (bool) ($cliOptions['no-vite'] ?? false);
        $viteConfig = (bool) ($configArray['vite']['enabled'] ?? true);
        $viteEnabled = !$noVite && $viteConfig;
        $vitePort = (int) ($configArray['vite']['port'] ?? 5173);

        // 7. HTTPS
        $httpsFlag = (bool) ($cliOptions['https'] ?? false);
        $httpsConfig = (bool) ($configArray['https']['enabled'] ?? false);
        $https = $httpsFlag || $httpsConfig;

        // 8. Security
        $allowPublicBind = (bool) ($configArray['security']['allow_public_bind'] ?? false);
        $blockProduction = (bool) ($configArray['security']['block_production'] ?? true);

        // 9. Flags
        $diagnose = (bool) ($cliOptions['diagnose'] ?? false);
        $json = (bool) ($cliOptions['json'] ?? false);
        $force = (bool) ($cliOptions['force'] ?? false);

        return new self(
            host: $host,
            port: $port,
            interface: $interface,
            autoPort: $autoPort,
            autoPortMin: $autoPortMin,
            autoPortMax: $autoPortMax,
            qr: $qr,
            viteEnabled: $viteEnabled,
            vitePort: $vitePort,
            https: $https,
            allowPublicBind: $allowPublicBind,
            blockProduction: $blockProduction,
            diagnose: $diagnose,
            json: $json,
            force: $force,
        );
    }
}
