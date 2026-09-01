<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\QR\Contracts;

interface QrCodeRendererInterface
{
    /**
     * Render the QR code 2D matrix into a terminal-printable string.
     *
     * @param array<int, array<int, int|bool>> $matrix
     */
    public function render(array $matrix): string;
}
