<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Mocks;

use Mrokwor\LaravelLan\Network\Contracts\InterfaceDetectorInterface;
use Mrokwor\LaravelLan\Network\NetworkInterface;

final class FakeInterfaceDetector implements InterfaceDetectorInterface
{
    /**
     * @param array<NetworkInterface> $interfaces
     */
    public function __construct(
        public array $interfaces = []
    ) {
    }

    public function detect(): array
    {
        return $this->interfaces;
    }
}
