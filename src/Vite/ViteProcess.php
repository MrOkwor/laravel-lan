<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Vite;

use Mrokwor\LaravelLan\Support\Platform;
use Symfony\Component\Process\Process;
use Throwable;

final class ViteProcess
{
    private ?Process $process = null;

    public function __construct(
        private string $lanIp,
        private int $port = 5173,
        private ?string $basePath = null,
    ) {
    }

    /**
     * Start the Vite development server in the background.
     *
     * @param callable|null $outputCallback fn(string $type, string $buffer): void
     */
    public function start(?callable $outputCallback = null): void
    {
        $basePath = $this->basePath ?? (function_exists('base_path') ? base_path() : getcwd());
        $command = $this->resolveViteCommand($basePath);

        $env = array_merge($_ENV, $_SERVER, [
            'VITE_HMR_HOST' => $this->lanIp,
            'LARAVEL_LAN_IP' => $this->lanIp,
            'VITE_HOST' => '0.0.0.0',
            'VITE_PORT' => (string) $this->port,
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
     * Terminate the Vite process cleanly.
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

    /**
     * Resolve the appropriate package manager command to start Vite.
     *
     * @return array<string>
     */
    private function resolveViteCommand(string $basePath): array
    {
        $isWindows = Platform::isWindows();

        if (file_exists("{$basePath}/pnpm-lock.yaml")) {
            $bin = $isWindows ? 'pnpm.cmd' : 'pnpm';
            return [$bin, 'run', 'dev', '--host', '0.0.0.0', '--port', (string) $this->port];
        }

        if (file_exists("{$basePath}/yarn.lock")) {
            $bin = $isWindows ? 'yarn.cmd' : 'yarn';
            return [$bin, 'dev', '--host', '0.0.0.0', '--port', (string) $this->port];
        }

        if (file_exists("{$basePath}/bun.lockb") || file_exists("{$basePath}/bun.lock")) {
            $bin = $isWindows ? 'bun.exe' : 'bun';
            return [$bin, 'run', 'dev', '--host', '0.0.0.0', '--port', (string) $this->port];
        }

        $bin = $isWindows ? 'npm.cmd' : 'npm';
        return [$bin, 'run', 'dev', '--', '--host', '0.0.0.0', '--port', (string) $this->port];
    }
}
