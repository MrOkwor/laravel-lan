<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\HTTPS\CertificateManager;
use Mrokwor\LaravelLan\Tests\TestCase;

final class CertificateManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test-lan-ssl-' . uniqid();
        @mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp test directory
        if (is_dir($this->tempDir)) {
            $files = glob("{$this->tempDir}/*") ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_generates_ssl_certificate_for_lan_ip(): void
    {
        $manager = new CertificateManager($this->tempDir);
        $result = $manager->ensureCertificate('192.168.1.42');

        $this->assertFileExists($result->certPath);
        $this->assertFileExists($result->keyPath);

        $certContent = (string) file_get_contents($result->certPath);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $certContent);

        $keyContent = (string) file_get_contents($result->keyPath);
        $this->assertTrue(
            str_contains($keyContent, 'BEGIN PRIVATE KEY') || str_contains($keyContent, 'BEGIN RSA PRIVATE KEY')
        );
    }

    public function test_reuses_existing_matching_certificate(): void
    {
        $manager = new CertificateManager($this->tempDir);
        $result1 = $manager->ensureCertificate('192.168.1.42');
        $mtime1 = filemtime($result1->certPath);

        sleep(1);

        $result2 = $manager->ensureCertificate('192.168.1.42');
        $mtime2 = filemtime($result2->certPath);

        $this->assertSame($mtime1, $mtime2);
    }
}
