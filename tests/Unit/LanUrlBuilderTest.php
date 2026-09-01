<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Network\LanUrlBuilder;
use PHPUnit\Framework\TestCase;

final class LanUrlBuilderTest extends TestCase
{
    public function test_builds_http_lan_url(): void
    {
        $builder = new LanUrlBuilder();
        $url = $builder->build('192.168.1.42', 8000);

        $this->assertSame('http://192.168.1.42:8000', $url);
    }

    public function test_builds_https_lan_url(): void
    {
        $builder = new LanUrlBuilder();
        $url = $builder->build('192.168.1.42', 8443, https: true);

        $this->assertSame('https://192.168.1.42:8443', $url);
    }

    public function test_omits_standard_ports(): void
    {
        $builder = new LanUrlBuilder();
        $this->assertSame('http://192.168.1.42', $builder->build('192.168.1.42', 80, https: false));
        $this->assertSame('https://192.168.1.42', $builder->build('192.168.1.42', 443, https: true));
    }

    public function test_builds_local_url(): void
    {
        $builder = new LanUrlBuilder();
        $this->assertSame('http://127.0.0.1:8000', $builder->buildLocal(8000));
        $this->assertSame('https://127.0.0.1:8000', $builder->buildLocal(8000, https: true));
    }
}
