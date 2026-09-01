<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Illuminate\Http\Request;
use Mrokwor\LaravelLan\Tests\TestCase;
use Mrokwor\LaravelLan\Vite\LanVite;
use Mrokwor\LaravelLan\Vite\ViteProcess;

final class LanViteHookTest extends TestCase
{
    public function test_instantiates_vite_process(): void
    {
        $viteProcess = new ViteProcess('192.168.1.42', 5173);
        $this->assertFalse($viteProcess->isRunning());
    }

    public function test_transform_url_replaces_0_0_0_0_with_localhost_for_local_requests(): void
    {
        $this->app->instance('request', Request::create('http://localhost:8000'));

        $url = 'http://0.0.0.0:5173/resources/css/app.css';
        $transformed = LanVite::transformUrl($url);

        $this->assertSame('http://localhost:5173/resources/css/app.css', $transformed);
    }

    public function test_transform_url_replaces_localhost_and_0_0_0_0_with_lan_ip_for_mobile_requests(): void
    {
        $this->app->instance('request', Request::create('http://192.168.1.6:8000'));

        $url1 = 'http://0.0.0.0:5173/resources/css/app.css';
        $url2 = 'http://localhost:5173/@vite/client';
        $url3 = 'http://127.0.0.1:5173/resources/js/app.js';

        $this->assertSame('http://192.168.1.6:5173/resources/css/app.css', LanVite::transformUrl($url1));
        $this->assertSame('http://192.168.1.6:5173/@vite/client', LanVite::transformUrl($url2));
        $this->assertSame('http://192.168.1.6:5173/resources/js/app.js', LanVite::transformUrl($url3));
    }
}
