<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Server;

use Closure;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class LaravelServer
{
    private ServerProcess $serverProcess;
    private bool $shouldStop = false;

    public function __construct(
        private ServerConfiguration $config,
        private ?\Mrokwor\LaravelLan\Vite\ViteProcess $viteProcess = null
    ) {
        $this->serverProcess = new ServerProcess($config);
    }

    /**
     * Start the server and monitor it until interrupted.
     */
    public function serve(?OutputInterface $output = null, ?Closure $onTick = null): int
    {
        $this->registerSignalHandlers();

        // 1. Start Vite process if requested
        if ($this->viteProcess !== null) {
            $this->viteProcess->start(function (string $type, string $buffer) use ($output) {
                if ($output !== null && $output->isVerbose()) {
                    $output->write("<fg=cyan>[Vite]</> {$buffer}");
                }
            });
        }

        // 2. Start Laravel server
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

        // Monitoring loop
        while (!$this->shouldStop && $this->serverProcess->isRunning()) {
            if ($onTick !== null) {
                $onTick();
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(100000); // 100ms
        }

        $this->stop();

        return (int) ($this->serverProcess->getExitCode() ?? 0);
    }

    /**
     * Stop all managed processes.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
        if ($this->viteProcess !== null) {
            $this->viteProcess->stop();
        }
        $this->serverProcess->stop();
    }

    public function isRunning(): bool
    {
        return $this->serverProcess->isRunning();
    }

    public function getServerProcess(): ServerProcess
    {
        return $this->serverProcess;
    }

    private function registerSignalHandlers(): void
    {
        register_shutdown_function(function () {
            $this->stop();
        });

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->stop();
            });
            pcntl_signal(SIGTERM, function () {
                $this->stop();
            });
        }
    }
}
