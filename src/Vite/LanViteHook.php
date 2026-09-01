<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Vite;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Request;
use Throwable;

final class LanViteHook
{
    /**
     * Register the Vite dynamic LAN asset URL transformer.
     * Rewrites http://localhost:5173 to http://<lan-ip>:5173 for incoming LAN requests.
     */
    public static function register(): void
    {
        if (!class_exists(Vite::class)) {
            return;
        }

        try {
            app()->resolving(Vite::class, function (Vite $vite) {
                if (method_exists($vite, 'createAssetPathsUsing')) {
                    $vite->createAssetPathsUsing(function (string $path, ?bool $secure = null) {
                        return self::transformDevServerUrl($path);
                    });
                }
            });
        } catch (Throwable) {
        }
    }

    /**
     * Transform localhost Vite dev server URLs to match the requesting LAN host.
     */
    public static function transformDevServerUrl(string $url): string
    {
        try {
            if (!class_exists(Request::class) || !app()->bound('request')) {
                return $url;
            }

            $requestHost = Request::getHost();
            if ($requestHost && $requestHost !== 'localhost' && $requestHost !== '127.0.0.1') {
                return str_replace(
                    ['://localhost:', '://127.0.0.1:'],
                    "://{$requestHost}:",
                    $url
                );
            }
        } catch (Throwable) {
        }

        return $url;
    }
}
