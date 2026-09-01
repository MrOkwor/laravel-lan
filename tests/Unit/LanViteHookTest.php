<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\Tests\TestCase;
use Mrokwor\LaravelLan\Vite\LanViteHook;
use Mrokwor\LaravelLan\Vite\ViteProcess;

final class LanViteHookTest extends TestCase
{
    public function test_instantiates_vite_process(): void
    {
        $viteProcess = new ViteProcess('192.168.1.42', 5173);
        $this->assertFalse($viteProcess->isRunning());
    }

    public function test_transform_dev_server_url_leaves_localhost_when_request_is_local(): void
    {
        $url = 'http://localhost:5173/@vite/client';
        $transformed = LanViteHook::transformDevServerUrl($url);

        // When running in test CLI without incoming HTTP request host, remains unmodified
        $this->assertSame($url, $transformed);
    }
}
