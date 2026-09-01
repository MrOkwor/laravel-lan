<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\HTTPS\CertificateManager;
use Mrokwor\LaravelLan\HTTPS\TlsProxy;
use Mrokwor\LaravelLan\Tests\TestCase;

final class TlsProxyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test-tls-proxy-' . uniqid();
        @mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob("{$this->tempDir}/*") ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_can_instantiate_and_bind_tls_proxy(): void
    {
        $certManager = new CertificateManager($this->tempDir);
        $cert = $certManager->ensureCertificate('127.0.0.1');

        $proxy = new TlsProxy(
            bindHost: '127.0.0.1',
            bindPort: 58443,
            backendHost: '127.0.0.1',
            backendPort: 58000,
            certPath: $cert->certPath,
            keyPath: $cert->keyPath,
        );

        $proxy->start();
        $proxy->tick();
        $proxy->stop();

        $this->assertTrue(true);
    }
}
