<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Server;

use Mrokwor\LaravelLan\Support\Platform;
use Symfony\Component\Process\Process;
use Throwable;

final class ServerProcess
{
    private ?Process $process = null;

    public function __construct(
        private ServerConfiguration $config
    ) {
    }

    /**
     * Start the underlying Laravel development server.
     *
     * @param callable|null $outputCallback fn(string $type, string $buffer): void
     */
    public function start(?callable $outputCallback = null): void
    {
        $basePath = $this->config->basePath ?? base_path();

        $command = [
            $this->config->phpBinary,
            'artisan',
            'serve',
            "--host={$this->config->host}",
            "--port={$this->config->port}",
        ];

        $env = array_merge($_ENV, $_SERVER, [
            'LARAVEL_LAN_ACTIVE' => '1',
            'LARAVEL_LAN_IP' => $this->config->selectedIp,
            'LARAVEL_LAN_URL' => $this->config->lanUrl,
        ]);

        $this->process = new Process(
            command: $command,
            cwd: $basePath,
            env: $env,
            timeout: null
        );

        $this->process->start($outputCallback);
    }

    /**
     * Terminate the server process cleanly.
     */
    public function stop(float $timeout = 3.0): void
    {
        if ($this->process === null || !$this->process->isRunning()) {
            return;
        }

        $pid = $this->process->getPid();

        try {
            $this->process->stop($timeout);
        } catch (Throwable) {
        }

        // On Windows, ensure child processes spawned by `artisan serve` are also killed
        if (Platform::isWindows() && $pid !== null) {
            try {
                @shell_exec("taskkill /F /T /PID {$pid} 2>NUL");
            } catch (Throwable) {
            }
        }
    }

    public function isRunning(): bool
    {
        return $this->process !== null && $this->process->isRunning();
    }

    public function getPid(): ?int
    {
        return $this->process?->getPid();
    }

    public function getExitCode(): ?int
    {
        return $this->process?->getExitCode();
    }

    public function getErrorOutput(): string
    {
        return $this->process?->getErrorOutput() ?? '';
    }

    public function getOutput(): string
    {
        return $this->process?->getOutput() ?? '';
    }
}
