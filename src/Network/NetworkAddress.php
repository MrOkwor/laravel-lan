<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

use InvalidArgumentException;
use Mrokwor\LaravelLan\Network\Enums\AddressFamily;

final readonly class NetworkAddress
{
    public string $ip;
    public AddressFamily $family;
    public ?string $netmask;

    public function __construct(string $ip, ?AddressFamily $family = null, ?string $netmask = null)
    {
        $cleanIp = trim($ip);

        // Strip zone index from IPv6 (e.g. fe80::1%en0 or fe80::1%12)
        if (str_contains($cleanIp, '%')) {
            $cleanIp = explode('%', $cleanIp)[0];
        }

        if (!filter_var($cleanIp, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Invalid IP address: {$ip}");
        }

        $this->ip = $cleanIp;
        $this->netmask = $netmask;

        if ($family !== null) {
            $this->family = $family;
        } else {
            $this->family = filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                ? AddressFamily::IPv6
                : AddressFamily::IPv4;
        }
    }

    public function isIpv4(): bool
    {
        return $this->family === AddressFamily::IPv4;
    }

    public function isIpv6(): bool
    {
        return $this->family === AddressFamily::IPv6;
    }

    public function isLoopback(): bool
    {
        if ($this->isIpv4()) {
            return str_starts_with($this->ip, '127.');
        }

        return $this->ip === '::1' || $this->ip === '0:0:0:0:0:0:0:1';
    }

    public function isLinkLocal(): bool
    {
        if ($this->isIpv4()) {
            return str_starts_with($this->ip, '169.254.');
        }

        return str_starts_with(strtolower($this->ip), 'fe80:');
    }

    public function isPrivate(): bool
    {
        if ($this->isLoopback() || $this->isLinkLocal()) {
            return false;
        }

        if ($this->isIpv4()) {
            $long = ip2long($this->ip);
            if ($long === false) {
                return false;
            }

            // 10.0.0.0/8 (10.0.0.0 to 10.255.255.255)
            $tenStart = ip2long('10.0.0.0');
            $tenEnd = ip2long('10.255.255.255');
            if ($long >= $tenStart && $long <= $tenEnd) {
                return true;
            }

            // 172.16.0.0/12 (172.16.0.0 to 172.31.255.255)
            $seventeenStart = ip2long('172.16.0.0');
            $seventeenEnd = ip2long('172.31.255.255');
            if ($long >= $seventeenStart && $long <= $seventeenEnd) {
                return true;
            }

            // 192.168.0.0/16 (192.168.0.0 to 192.168.255.255)
            $oneNineTwoStart = ip2long('192.168.0.0');
            $oneNineTwoEnd = ip2long('192.168.255.255');
            if ($long >= $oneNineTwoStart && $long <= $oneNineTwoEnd) {
                return true;
            }

            return false;
        }

        // IPv6 Unique Local Address fc00::/7 (fc00:: - fdff::)
        $firstHex = substr(strtolower($this->ip), 0, 2);
        return $firstHex === 'fc' || $firstHex === 'fd';
    }

    public function isPublic(): bool
    {
        return !$this->isPrivate() && !$this->isLoopback() && !$this->isLinkLocal();
    }

    public function isUsableLan(): bool
    {
        return $this->isIpv4() && $this->isPrivate();
    }

    public function toString(): string
    {
        return $this->ip;
    }

    public function __toString(): string
    {
        return $this->ip;
    }
}
