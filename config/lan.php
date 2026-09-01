<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Host & Port
    |--------------------------------------------------------------------------
    |
    | The host and port on which the Laravel development server will bind.
    | Binding to 0.0.0.0 enables listening on all available network interfaces.
    |
    */
    'host' => env('LARAVEL_LAN_HOST', '0.0.0.0'),
    'port' => (int) env('LARAVEL_LAN_PORT', 8000),

    /*
    |--------------------------------------------------------------------------
    | Preferred Interface
    |--------------------------------------------------------------------------
    |
    | Optionally force a specific network interface name (e.g. 'en0', 'wlan0',
    | 'Wi-Fi', 'Ethernet'). When null, Laravel LAN automatically selects the
    | best available active private LAN interface.
    |
    */
    'interface' => env('LARAVEL_LAN_INTERFACE', null),

    /*
    |--------------------------------------------------------------------------
    | Automatic Port Selection
    |--------------------------------------------------------------------------
    |
    | When enabled (default), if the default port is occupied, Laravel LAN
    | will automatically scan for the next available port within the specified
    | range instead of failing.
    |
    */
    'auto_port' => (bool) env('LARAVEL_LAN_AUTO_PORT', true),
    'auto_port_range' => [
        'min' => (int) env('LARAVEL_LAN_PORT_MIN', 8000),
        'max' => (int) env('LARAVEL_LAN_PORT_MAX', 8100),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Display
    |--------------------------------------------------------------------------
    |
    | Whether to render a QR code in the terminal upon server startup.
    |
    */
    'qr' => (bool) env('LARAVEL_LAN_QR', true),

    /*
    |--------------------------------------------------------------------------
    | Vite Integration
    |--------------------------------------------------------------------------
    |
    | Settings for coordinating with Vite development server.
    |
    */
    'vite' => [
        'enabled' => (bool) env('LARAVEL_LAN_VITE', true),
        'port' => (int) env('LARAVEL_LAN_VITE_PORT', 5173),
        'autostart' => (bool) env('LARAVEL_LAN_VITE_AUTOSTART', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Safeguards to protect your development environment and avoid exposing
    | production servers.
    |
    */
    'security' => [
        'allow_public_bind' => (bool) env('LARAVEL_LAN_ALLOW_PUBLIC_BIND', false),
        'block_production' => (bool) env('LARAVEL_LAN_BLOCK_PRODUCTION', true),
    ],
];
