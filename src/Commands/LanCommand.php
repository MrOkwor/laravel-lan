<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Commands;

use Illuminate\Console\Command;
use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\DiagnosticRunner;
use Mrokwor\LaravelLan\Network\LanUrlBuilder;
use Mrokwor\LaravelLan\Network\NetworkInterfaceDetector;
use Mrokwor\LaravelLan\Network\NetworkSelector;
use Mrokwor\LaravelLan\Network\PortChecker;
use Mrokwor\LaravelLan\QR\QrCodeGenerator;
use Mrokwor\LaravelLan\Server\LaravelServer;
use Mrokwor\LaravelLan\Server\ServerConfiguration;
use Throwable;

final class LanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan
        {--host= : The host address to serve the application on (default: 0.0.0.0)}
        {--port= : The port to serve the application on}
        {--interface= : Force a specific network interface name or IP}
        {--no-auto-port : Disable automatic port fallback when requested port is occupied}
        {--https : Enable HTTPS for local LAN serving (experimental)}
        {--no-qr : Disable QR code rendering in the terminal}
        {--with-vite : Automatically start and manage the Vite development server alongside Laravel LAN}
        {--no-vite : Disable Vite integration}
        {--diagnose : Run connectivity and environment diagnostics instead of starting the server}
        {--json : Output information in JSON format}
        {--force : Force server startup even if APP_ENV is production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the Laravel application over the local network (LAN) with a scannable QR code';

    /**
     * Execute the console command.
     */
    public function handle(
        NetworkInterfaceDetector $detector,
        NetworkSelector $selector,
        PortChecker $portChecker,
        LanUrlBuilder $urlBuilder,
        QrCodeGenerator $qrGenerator,
        DiagnosticRunner $diagnosticRunner
    ): int {
        $configArray = (array) config('lan', []);
        $config = LanConfiguration::resolve($this->options(), $configArray);

        // 1. Diagnostics mode
        if ($config->diagnose) {
            return $this->handleDiagnostics($config, $diagnosticRunner);
        }

        // 2. Production safety guard
        if ($this->isProductionEnvironment() && $config->blockProduction && !$config->force) {
            $this->components->error("APP_ENV is set to 'production'. Laravel LAN is intended for local development only.");
            $this->components->warn('To override this check if intentional, pass the --force option.');
            return self::FAILURE;
        }

        // 3. Detect and select network interface
        try {
            $selection = $selector->select(
                preferredInterface: $config->interface,
                promptCallback: $this->input->isInteractive() ? function (array $options, int $default): int {
                    $choice = $this->choice('Multiple network interfaces detected. Select an interface to use:', $options, (string) $default);
                    return array_search($choice, $options, true) ?: $default;
                } : null
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $selectedIface = $selection['interface'];
        $selectedAddress = $selection['address'];
        $selectedIp = $selectedAddress->ip;

        // 4. Resolve port
        try {
            $port = $portChecker->resolvePort(
                preferredPort: $config->port,
                autoPort: $config->autoPort,
                min: $config->autoPortMin,
                max: $config->autoPortMax,
                host: $config->host
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        // 5. Build URLs
        $localUrl = $urlBuilder->buildLocal($port, $config->https);
        $lanUrl = $urlBuilder->build($selectedIp, $port, $config->https);
        $viteLanUrl = $config->viteEnabled ? $urlBuilder->build($selectedIp, $config->vitePort) : null;

        // 6. JSON output mode
        if ($config->json) {
            $json = (string) json_encode([
                'host' => $config->host,
                'port' => $port,
                'interface' => $selectedIface->name,
                'interface_type' => $selectedIface->type->value,
                'ip' => $selectedIp,
                'local_url' => $localUrl,
                'lan_url' => $lanUrl,
                'vite_url' => $viteLanUrl,
                'with_vite' => $config->withVite,
                'https' => $config->https,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $lines = preg_split('/\r?\n/', $json) ?: [$json];
            foreach ($lines as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        // 7. Render banner and info
        $this->renderHeader();
        $this->renderNetworkSummary($selectedIface->displayName, $selectedIp, $port, $localUrl, $lanUrl, $config->withVite ? $viteLanUrl : null);

        // 8. Generate and render QR code
        if ($config->qr) {
            $this->renderQrCode($lanUrl, $qrGenerator);
        }

        $this->line('  <comment>Press Ctrl+C to stop the server.</comment>');
        $this->newLine();

        // 9. Start Server
        $serverConfig = new ServerConfiguration(
            host: $config->host,
            port: $port,
            selectedIp: $selectedIp,
            localUrl: $localUrl,
            lanUrl: $lanUrl,
            interfaceName: $selectedIface->name,
            isHttps: $config->https,
        );

        $viteProcess = $config->withVite
            ? new \Mrokwor\LaravelLan\Vite\ViteProcess(lanIp: $selectedIp, port: $config->vitePort)
            : null;

        $server = new LaravelServer($serverConfig, $viteProcess);

        return $server->serve($this->output);
    }

    private function handleDiagnostics(LanConfiguration $config, DiagnosticRunner $diagnosticRunner): int
    {
        $results = $diagnosticRunner->run($config);

        if ($config->json) {
            $payload = array_map(fn ($res) => [
                'name' => $res->name,
                'status' => $res->status->value,
                'message' => $res->message,
                'hint' => $res->hint,
                'data' => $res->data,
            ], $results);

            $json = (string) json_encode([
                'diagnostics' => $payload,
                'has_failures' => $diagnosticRunner->hasFailures($results),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $lines = preg_split('/\r?\n/', $json) ?: [$json];
            foreach ($lines as $line) {
                $this->line($line);
            }

            return $diagnosticRunner->hasFailures($results) ? self::FAILURE : self::SUCCESS;
        }

        $diagnosticRunner->render($results, $this->output);

        return $diagnosticRunner->hasFailures($results) ? self::FAILURE : self::SUCCESS;
    }

    private function isProductionEnvironment(): bool
    {
        if (function_exists('app') && app()->bound('env')) {
            return app()->isProduction();
        }

        return strtolower(getenv('APP_ENV') ?: '') === 'production';
    }

    private function renderHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=bright-white;bg=red;options=bold>  LARAVEL LAN  </> <fg=gray>Local networking for Laravel</>');
        $this->newLine();
    }

    private function renderNetworkSummary(string $interface, string $ip, int $port, string $localUrl, string $lanUrl, ?string $viteUrl = null): void
    {
        $this->line("  <fg=green>✓</> <options=bold>Interface:</> {$interface}");
        $this->line("  <fg=green>✓</> <options=bold>Port:</>      {$port}");
        if ($viteUrl !== null) {
            $this->line("  <fg=green>✓</> <options=bold>Vite HMR:</>  {$viteUrl}");
        }
        $this->newLine();

        $this->line("  <options=bold>Local:</>      <fg=cyan;options=underscore>{$localUrl}</>");
        $this->line("  <options=bold>LAN:</>        <fg=bright-green;options=bold,underscore>{$lanUrl}</>");
        $this->newLine();
    }

    private function renderQrCode(string $url, QrCodeGenerator $qrGenerator): void
    {
        $qr = $qrGenerator->generate($url);

        if ($qr !== null) {
            $this->line('  <options=bold>Scan with your phone camera:</>');
            $this->newLine();

            // Indent each line of the QR code
            $lines = preg_split('/\r?\n/', $qr) ?: [$qr];
            foreach ($lines as $line) {
                $this->line("  {$line}");
            }
            $this->newLine();
        }
    }
}
