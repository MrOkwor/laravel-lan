<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\HTTPS;

final readonly class CertificateResult
{
    public function __construct(
        public string $certPath,
        public string $keyPath,
        public bool $isTrusted = false,
        public bool $isMkcert = false,
    ) {
    }
}
