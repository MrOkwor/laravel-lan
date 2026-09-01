<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Enums;

enum AddressFamily: string
{
    case IPv4 = 'IPv4';
    case IPv6 = 'IPv6';
}
