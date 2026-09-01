<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Server;

final readonly class ServerConfiguration
{
    public function __construct(
        public string $host,
        public int $port,
        public string $selectedIp,
        public string $localUrl,
        public string $lanUrl,
        public string $interfaceName,
        public bool $isHttps = false,
        public string $phpBinary = PHP_BINARY,
        public ?string $basePath = null,
    ) {
    }
}
