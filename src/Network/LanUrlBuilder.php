<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

final class LanUrlBuilder
{
    /**
     * Build the LAN URL for accessing the application.
     */
    public function build(string $ip, int $port, bool $https = false, ?string $path = null): string
    {
        $scheme = $https ? 'https' : 'http';

        // Wrap IPv6 addresses in square brackets if necessary
        $host = str_contains($ip, ':') && !str_starts_with($ip, '[') ? "[{$ip}]" : $ip;

        // Omit standard ports
        $portSuffix = ($https && $port === 443) || (!$https && $port === 80)
            ? ''
            : ":{$port}";

        $cleanPath = $path ? '/' . ltrim($path, '/') : '';

        return "{$scheme}://{$host}{$portSuffix}{$cleanPath}";
    }

    /**
     * Build the Localhost URL for local access on the host machine.
     */
    public function buildLocal(int $port, bool $https = false, ?string $path = null): string
    {
        return $this->build('127.0.0.1', $port, $https, $path);
    }
}
