<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Server;

use Closure;
use Mrokwor\LaravelLan\HTTPS\TlsProxy;
use Mrokwor\LaravelLan\Support\Platform;
use Mrokwor\LaravelLan\Vite\ViteProcess;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class LaravelServer
{
    private ServerProcess $serverProcess;
    private bool $shouldStop = false;

    public function __construct(
        private ServerConfiguration $config,
        private ?ViteProcess $viteProcess = null,
        private ?Closure $onShowQr = null,
        private ?Closure $onDiagnose = null,
        private ?TlsProxy $tlsProxy = null,
    ) {
        $this->serverProcess = new ServerProcess($config);
    }

    /**
     * Start the server and monitor it until interrupted.
     */
    public function serve(?OutputInterface $output = null, ?Closure $onTick = null): int
    {
        $this->registerSignalHandlers();

        // 1. Start TLS Proxy if HTTPS is enabled
        if ($this->tlsProxy !== null) {
            try {
                $this->tlsProxy->start();
            } catch (Throwable $e) {
                if ($output !== null) {
                    $output->writeln("<error>Failed to start TLS proxy:</error> {$e->getMessage()}");
                }
                return 1;
            }
        }

        // 2. Start Vite process if requested
        if ($this->viteProcess !== null) {
            $this->viteProcess->start(function (string $type, string $buffer) use ($output) {
                if ($output !== null && $output->isVerbose()) {
                    $output->write("<fg=cyan>[Vite]</> {$buffer}");
                }
            });
        }

        // 3. Start Laravel server
        $this->serverProcess->start(function (string $type, string $buffer) use ($output) {
            if ($output !== null && $output->isVerbose()) {
                $output->write($buffer);
            }
        });

        // Small wait to ensure process didn't immediately crash on startup
        usleep(200000); // 200ms

        if (!$this->serverProcess->isRunning()) {
            $error = trim($this->serverProcess->getErrorOutput());
            if ($output !== null) {
                $output->writeln("<error>Failed to start Laravel development server:</error>");
                if ($error !== '') {
                    $output->writeln("  {$error}");
                }
            }
            $this->stop();
            return 1;
        }

        // Non-blocking keyboard check if interactive terminal
        if (defined('STDIN') && is_resource(STDIN) && function_exists('stream_set_blocking')) {
            try {
                @stream_set_blocking(STDIN, false);
            } catch (Throwable) {
            }
        }

        // Monitoring loop
        while (!$this->shouldStop && $this->serverProcess->isRunning()) {
            if ($onTick !== null) {
                $onTick();
            }

            if ($this->tlsProxy !== null) {
                $this->tlsProxy->tick();
            }

            // Check for interactive keystrokes
            if (defined('STDIN') && is_resource(STDIN)) {
                $char = @fgetc(STDIN);
                if ($char !== false && $char !== '') {
                    $char = strtolower(trim($char));
                    if ($char === 'r') {
                        if ($output !== null) {
                            $output->writeln("  <fg=yellow>↻ Restarting Laravel development server...</>");
                        }
                        $this->serverProcess->stop();
                        $this->serverProcess = new ServerProcess($this->config);
                        $this->serverProcess->start();
                    } elseif ($char === 'q' && $this->onShowQr !== null) {
                        ($this->onShowQr)();
                    } elseif ($char === 'd' && $this->onDiagnose !== null) {
                        ($this->onDiagnose)();
                    } elseif ($char === 'h') {
                        if ($output !== null) {
                            $output->writeln("  <options=bold>Shortcuts:</> [r] Restart server  [q] Show QR code  [d] Run diagnostics  [Ctrl+C] Quit");
                        }
                    }
                }
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // When TLS proxy is active, use smaller usleep for snappy packet relay
            usleep($this->tlsProxy !== null ? 2000 : 100000); // 2ms if proxying TLS, else 100ms
        }

        $this->stop();

        return (int) ($this->serverProcess->getExitCode() ?? 0);
    }

    /**
     * Stop all managed processes and proxy sockets.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
        if ($this->tlsProxy !== null) {
            $this->tlsProxy->stop();
        }
        if ($this->viteProcess !== null) {
            $this->viteProcess->stop();
        }
        $this->serverProcess->stop();
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, fn () => $this->stop());
            pcntl_signal(SIGTERM, fn () => $this->stop());
        }

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(fn (int $event) => $this->stop());
        }

        register_shutdown_function(fn () => $this->stop());
    }
}
