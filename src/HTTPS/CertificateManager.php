<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\HTTPS;

use Mrokwor\LaravelLan\Support\Platform;
use Symfony\Component\Process\Process;
use Throwable;

final class CertificateManager
{
    public function __construct(
        private ?string $storagePath = null
    ) {
    }

    /**
     * Ensure an SSL certificate and private key exist for the given LAN IP.
     * Prefers mkcert if installed, otherwise generates an OpenSSL certificate.
     */
    public function ensureCertificate(string $lanIp): CertificateResult
    {
        $dir = $this->getCertDirectory();
        $certFile = "{$dir}/cert.pem";
        $keyFile = "{$dir}/key.pem";

        // Check if existing certificate is valid for this IP
        if (file_exists($certFile) && file_exists($keyFile)) {
            $info = @openssl_x509_parse((string) @file_get_contents($certFile));
            $subjectAltNames = (string) ($info['extensions']['subjectAltName'] ?? '');
            if (str_contains($subjectAltNames, $lanIp)) {
                return new CertificateResult(
                    certPath: $certFile,
                    keyPath: $keyFile,
                    isTrusted: $this->hasMkcert(),
                    isMkcert: $this->hasMkcert(),
                );
            }
        }

        // Try mkcert first
        if ($this->hasMkcert()) {
            $created = $this->generateWithMkcert($lanIp, $certFile, $keyFile);
            if ($created) {
                return new CertificateResult(
                    certPath: $certFile,
                    keyPath: $keyFile,
                    isTrusted: true,
                    isMkcert: true,
                );
            }
        }

        // Fallback to PHP OpenSSL self-signed certificate
        $this->generateSelfSigned($lanIp, $certFile, $keyFile);

        return new CertificateResult(
            certPath: $certFile,
            keyPath: $keyFile,
            isTrusted: false,
            isMkcert: false,
        );
    }

    /**
     * Check if mkcert is installed on the host system.
     */
    public function hasMkcert(): bool
    {
        try {
            $process = new Process([Platform::isWindows() ? 'where' : 'which', 'mkcert']);
            $process->run();
            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function generateWithMkcert(string $lanIp, string $certFile, string $keyFile): bool
    {
        try {
            $process = new Process([
                'mkcert',
                '-cert-file', $certFile,
                '-key-file', $keyFile,
                $lanIp,
                'localhost',
                '127.0.0.1',
            ]);
            $process->run();

            return $process->isSuccessful() && file_exists($certFile) && file_exists($keyFile);
        } catch (Throwable) {
            return false;
        }
    }

    private function generateSelfSigned(string $lanIp, string $certFile, string $keyFile): void
    {
        $dn = [
            'commonName' => $lanIp,
            'organizationName' => 'Laravel LAN Local Development',
        ];

        $sanConfig = "
[req]
distinguished_name = req_distinguished_name
x509_extensions = v3_req
prompt = no
[req_distinguished_name]
CN = {$lanIp}
[v3_req]
subjectAltName = @alt_names
[alt_names]
IP.1 = {$lanIp}
IP.2 = 127.0.0.1
DNS.1 = localhost
";

        $tempConfig = tempnam(sys_get_temp_dir(), 'san');
        file_put_contents((string) $tempConfig, $sanConfig);

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $tempConfig,
        ]);

        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha256',
            'config' => $tempConfig,
        ]);

        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, [
            'digest_alg' => 'sha256',
            'config' => $tempConfig,
        ]);

        openssl_x509_export($x509, $certOut);
        openssl_pkey_export($privateKey, $keyOut, null, ['config' => $tempConfig]);

        file_put_contents($certFile, $certOut);
        file_put_contents($keyFile, $keyOut);

        @unlink((string) $tempConfig);
    }

    private function getCertDirectory(): string
    {
        if ($this->storagePath !== null) {
            $dir = $this->storagePath;
        } elseif (function_exists('storage_path')) {
            $dir = storage_path('framework/cache/laravel-lan-ssl');
        } else {
            $dir = sys_get_temp_dir() . '/laravel-lan-ssl';
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
