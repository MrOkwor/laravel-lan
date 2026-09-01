<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Vite;

use Illuminate\Foundation\Vite as BaseVite;
use Illuminate\Support\Facades\Request;
use Throwable;

final class LanVite extends BaseVite
{
    /**
     * Get the path to a given asset when running in HMR mode,
     * dynamically translating 0.0.0.0, localhost, or 127.0.0.1 to the requesting host.
     *
     * @param string $asset
     * @return string
     */
    protected function hotAsset($asset)
    {
        $url = parent::hotAsset($asset);

        return self::transformUrl($url);
    }

    /**
     * Transform Vite dev server URLs to match the client's requesting host.
     */
    public static function transformUrl(string $url): string
    {
        try {
            if (!class_exists(Request::class) || !app()->bound('request')) {
                return str_replace('://0.0.0.0:', '://localhost:', $url);
            }

            $requestHost = Request::getHost();
            if ($requestHost) {
                // If accessed via localhost / 127.0.0.1, convert 0.0.0.0 to localhost
                if ($requestHost === 'localhost' || $requestHost === '127.0.0.1') {
                    return str_replace('://0.0.0.0:', '://localhost:', $url);
                }

                // If accessed via LAN IP (e.g. 192.168.1.6), convert 0.0.0.0 / localhost / 127.0.0.1 to LAN IP
                return (string) preg_replace(
                    '#://(0\.0\.0\.0|localhost|127\.0\.0\.1)(:\d+)?#',
                    "://{$requestHost}$2",
                    $url
                );
            }
        } catch (Throwable) {
        }

        return str_replace('://0.0.0.0:', '://localhost:', $url);
    }
}
