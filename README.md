# Laravel LAN

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrokwor/laravel-lan.svg?style=flat-square)](https://packagist.org/packages/mrokwor/laravel-lan)
[![Total Downloads](https://img.shields.io/packagist/dt/mrokwor/laravel-lan.svg?style=flat-square)](https://packagist.org/packages/mrokwor/laravel-lan)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Test your Laravel application on real devices over your local network with a single command.

```bash
php artisan lan
```

---

## Overview

When building mobile and responsive web applications with Laravel, testing on actual physical devices (smartphones, tablets) on the same local network is essential.

**Typical manual workflow:**
1. Run operating system commands (`ipconfig`, `ifconfig`, `ip addr`) to identify active network adapters.
2. Filter out loopback, WSL, Docker bridges, and VPN interfaces.
3. Start `php artisan serve --host=0.0.0.0 --port=8000`.
4. Manually type the local IP address on mobile devices.
5. Configure Vite host settings for assets and Hot Module Replacement (HMR).

**Laravel LAN workflow:**
```bash
php artisan lan
```
Scan the generated QR code in your terminal to open the application directly on your device.

---

## Features

- **Zero-Configuration Network Discovery**: Automatically detects active Wi-Fi and Ethernet adapters, ignoring virtual adapters (Docker, WSL, VMware, loopback).
- **Automatic Port Fallback**: Automatically scans and binds to the next available port if the default port is occupied.
- **Terminal QR Code**: Renders a clean, high-density terminal QR code for instant mobile camera scanning (offline, zero remote requests).
- **Safe by Default**: Restricts access to private local subnets (`192.168.x.x`, `10.x.x.x`, `172.16-31.x.x`) and prevents starting in `production` environments without explicit confirmation.
- **Vite Integration**: Detects Vite and provides configuration hints for Hot Module Replacement across LAN devices.
- **Built-in Diagnostics**: Includes a comprehensive diagnostic suite (`php artisan lan --diagnose`) for troubleshooting network interfaces, firewalls, and ports.
- **Cross-Platform**: Full support for Windows, macOS, and Linux.

---

## Installation

Install the package as a development dependency:

```bash
composer require mrokwor/laravel-lan --dev
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-lan-config
```

---

## Quick Start

Start serving your application over the local network:

```bash
php artisan lan
```

### Sample Terminal Output:

```
  LARAVEL LAN  Local networking for Laravel

  ✓ Interface: Wi-Fi (Intel(R) Wi-Fi 6 AX200)
  ✓ Port:      8000

  Local:       http://127.0.0.1:8000
  LAN:         http://192.168.1.42:8000

  Scan with your phone camera:

  █▀▀▀▀▀█ ██ ██ █▀▀▀▀▀█  
  █ ███ █ ▀█▄ ▀ █ ███ █  
  █ ▀▀▀ █ ▀▀ ▀▀ █ ▀▀▀ █  
  ▀▀▀▀▀▀▀ ▀ ▀ ▀ ▀▀▀▀▀▀▀  
  ▀ ▄▀██▀███ █▄█ ▄█▄▀██  
  ▄▄█▄▄█▀█▄█ ▀█▄▄█▄▀ ▄▀  
  ▀▀    ▀▀▄ ▄▀███▀▀▀███  
  █▀▀▀▀▀█ █▀ ▀  ▄  ▀█ ▀  
  █ ███ █ ██▀ ▄██▄█ ▀▀   
  █ ▀▀▀ █  ▄▀██▀ █▀█ ██  
  ▀▀▀▀▀▀▀ ▀ ▀ ▀▀ ▀▀ ▀▀   

  Press Ctrl+C to stop the server.
```

---

## Command Options

| Option | Description | Example |
|---|---|---|
| `--port=` | Specify a custom port | `php artisan lan --port=8080` |
| `--interface=` | Specify a network interface name or IP | `php artisan lan --interface=wlan0` |
| `--host=` | Custom bind host (default: `0.0.0.0`) | `php artisan lan --host=0.0.0.0` |
| `--no-auto-port` | Disable automatic port fallback when occupied | `php artisan lan --no-auto-port` |
| `--no-qr` | Disable QR code rendering in terminal | `php artisan lan --no-qr` |
| `--diagnose` | Run connectivity and environment diagnostics | `php artisan lan --diagnose` |
| `--json` | Output network information as JSON | `php artisan lan --json` |
| `--force` | Force startup even if `APP_ENV=production` | `php artisan lan --force` |

---

## Diagnostics

To troubleshoot network, adapter, or port issues, run:

```bash
php artisan lan --diagnose
```

Sample diagnostics output:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
             Laravel LAN Diagnostics              
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

 ✓ Environment Safety
   Application environment is 'local'.

 ✓ Host Binding
   Server host is bound to '0.0.0.0' (all available network interfaces).

 ✓ LAN Network Interface
   Active LAN interface(s) available: Wi-Fi (192.168.1.42)

 ✓ Port Availability
   Port 8000 is available for binding on 0.0.0.0.

 ⚠ Vite Integration
   Vite detected, but server.host may be bound only to localhost.
   Tip: To ensure CSS/JS HMR works on your mobile device, add `server: { host: '0.0.0.0' }` to vite.config.js.

 ℹ Firewall & Network
   Ensure Windows Defender Firewall allows incoming connections for PHP on port 8000.
   Tip: If connecting fails from your phone, check if Wi-Fi network profile is set to "Private" and Client Isolation is off.

Result: All essential checks passed. Laravel LAN is ready to start!
```

---

## Vite & Hot Module Replacement (HMR)

To enable Vite asset loading and live reload across devices on your local network, configure the `server` block in `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '0.0.0.0', // Listen on all network interfaces
        hmr: {
            host: '192.168.1.42', // Computer LAN IP address
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

---

## Configuration

Publishing `config/lan.php` enables customizing package defaults:

```php
return [
    'host' => env('LARAVEL_LAN_HOST', '0.0.0.0'),
    'port' => (int) env('LARAVEL_LAN_PORT', 8000),
    'interface' => env('LARAVEL_LAN_INTERFACE', null),

    'auto_port' => (bool) env('LARAVEL_LAN_AUTO_PORT', true),
    'auto_port_range' => [
        'min' => (int) env('LARAVEL_LAN_PORT_MIN', 8000),
        'max' => (int) env('LARAVEL_LAN_PORT_MAX', 8100),
    ],

    'qr' => (bool) env('LARAVEL_LAN_QR', true),

    'vite' => [
        'enabled' => (bool) env('LARAVEL_LAN_VITE', true),
        'port' => (int) env('LARAVEL_LAN_VITE_PORT', 5173),
    ],

    'security' => [
        'allow_public_bind' => (bool) env('LARAVEL_LAN_ALLOW_PUBLIC_BIND', false),
        'block_production' => (bool) env('LARAVEL_LAN_BLOCK_PRODUCTION', true),
    ],
];
```

---

## Troubleshooting

- **Device cannot connect:** Verify that both computer and mobile device are connected to the same Wi-Fi network.
- **Wi-Fi Client Isolation:** Some guest or office networks block device-to-device traffic. Connect to a private Wi-Fi network or a personal hotspot.
- **Firewall settings:** Ensure the host OS firewall allows inbound TCP connections for PHP on the target port.
- **VPN software:** Active VPN connections can reroute local subnet traffic. Use split tunneling or temporarily disconnect the VPN.

---

## Testing

Run the test suite via PHPUnit:

```bash
composer test
```

---

## Contributing

Contributions are welcome. Please refer to [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for details.
