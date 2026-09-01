<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Enums;

enum InterfaceType: string
{
    case Wifi = 'wifi';
    case Ethernet = 'ethernet';
    case Virtual = 'virtual';
    case Loopback = 'loopback';
    case Vpn = 'vpn';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wifi => 'Wi-Fi',
            self::Ethernet => 'Ethernet',
            self::Virtual => 'Virtual',
            self::Loopback => 'Loopback',
            self::Vpn => 'VPN',
            self::Other => 'Network',
        };
    }
}
