<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Network\Contracts;

use Mrokwor\LaravelLan\Network\NetworkInterface;

interface InterfaceDetectorInterface
{
    /**
     * Detect and return available network interfaces.
     *
     * @return array<NetworkInterface>
     */
    public function detect(): array;
}
