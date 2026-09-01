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
        $localUrl = $urlBuilder->buildLocal($port);
        $lanUrl = $urlBuilder->build($selectedIp, $port);
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

        $this->line('  <comment>Press Ctrl+C to stop the server.</comment> <fg=gray>(Press \'h\' for shortcuts)</>');
        $this->newLine();

        // 9. Start Server
        $serverConfig = new ServerConfiguration(
            host: $config->host,
            port: $port,
            selectedIp: $selectedIp,
            localUrl: $localUrl,
            lanUrl: $lanUrl,
            interfaceName: $selectedIface->name,
        );

        $viteProcess = $config->withVite
            ? new \Mrokwor\LaravelLan\Vite\ViteProcess(lanIp: $selectedIp, port: $config->vitePort)
            : null;

        $server = new LaravelServer(
            config: $serverConfig,
            viteProcess: $viteProcess,
            onShowQr: fn () => $this->renderQrCode($lanUrl, $qrGenerator),
            onDiagnose: fn () => $this->handleDiagnostics($config, $diagnosticRunner),
        );

        return $server->serve($this->output);
    }

    private function handleDiagnostics(LanConfiguration $config, DiagnosticRunner $diagnosticRunner): int
    {
        $results = $diagnosticRunner->run($config);

        if ($config->json) {
            $json = (string) json_encode([
                'status' => $diagnosticRunner->hasFailures($results) ? 'failure' : 'success',
                'diagnostics' => array_map(fn ($r) => $r->toArray(), $results),
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
        return $this->laravel->environment('production');
    }

    private function renderHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=bright-white;bg=blue;options=bold>  LARAVEL LAN  </> <fg=gray>Local Network Development Server</>');
        $this->newLine();
    }

    private function renderNetworkSummary(
        string $interfaceName,
        string $ip,
        int $port,
        string $localUrl,
        string $lanUrl,
        ?string $viteUrl = null
    ): void {
        $this->line("  <fg=gray>Interface:</> <options=bold>{$interfaceName}</>");
        $this->line("  <fg=gray>Local IP:</>   <fg=cyan;options=bold>{$ip}</>");
        $this->line("  <fg=gray>Port:</>       <fg=yellow;options=bold>{$port}</>");
        $this->newLine();
        $this->line("  <options=bold>Local URL:</>   <fg=bright-blue;options=underscore>{$localUrl}</>");
        $this->line("  <options=bold>LAN URL:</>     <fg=bright-green;options=bold,underscore>{$lanUrl}</>");

        if ($viteUrl !== null) {
            $this->line("  <options=bold>Vite URL:</>    <fg=bright-magenta;options=bold,underscore>{$viteUrl}</> <fg=gray>(HMR active)</>");
        }

        $this->newLine();
    }

    private function renderQrCode(string $url, QrCodeGenerator $qrGenerator): void
    {
        $this->line('  <options=bold>Scan QR code with your phone:</>');
        $this->newLine();

        $qrLines = $qrGenerator->generateLines($url);
        foreach ($qrLines as $line) {
            $this->line($line);
        }

        $this->newLine();
    }
}
