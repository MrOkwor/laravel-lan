<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network;

use Mrokwor\LaravelLan\Network\Enums\InterfaceType;

final readonly class NetworkInterface
{
    /**
     * @param array<NetworkAddress> $addresses
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public InterfaceType $type,
        public array $addresses = [],
        public bool $isUp = true,
        public bool $isWireless = false,
        public bool $isVirtual = false,
        public bool $isLoopback = false,
        public int $priority = 0,
        public ?string $macAddress = null,
    ) {
    }

    /**
     * @return array<NetworkAddress>
     */
    public function getAddresses(): array
    {
        return $this->addresses;
    }

    /**
     * Get the best private IPv4 address associated with this interface.
     */
    public function getPreferredIpv4(): ?NetworkAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->isUsableLan()) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Determine if this interface is suitable for serving a LAN application.
     */
    public function isUsableLan(): bool
    {
        if (!$this->isUp || $this->isLoopback || $this->isVirtual) {
            return false;
        }

        return $this->getPreferredIpv4() !== null;
    }

    /**
     * Check if the interface contains any private IPv4 address.
     */
    public function hasPrivateIpv4(): bool
    {
        return $this->getPreferredIpv4() !== null;
    }
}
