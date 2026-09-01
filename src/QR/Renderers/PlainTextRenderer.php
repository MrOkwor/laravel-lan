<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\QR\Renderers;

use Mrokwor\LaravelLan\QR\Contracts\QrCodeRendererInterface;

final class PlainTextRenderer implements QrCodeRendererInterface
{
    public function __construct(
        private string $dark = '##',
        private string $light = '  ',
        private int $quietZone = 2
    ) {
    }

    /**
     * Render matrix using plain ASCII characters.
     *
     * @param array<int, array<int, int|bool>> $matrix
     */
    public function render(array $matrix): string
    {
        $height = count($matrix);
        if ($height === 0) {
            return '';
        }

        $width = count($matrix[0]);
        $paddedWidth = $width + ($this->quietZone * 2);

        $lines = [];

        // Top quiet zone
        for ($i = 0; $i < $this->quietZone; $i++) {
            $lines[] = str_repeat($this->light, $paddedWidth);
        }

        for ($y = 0; $y < $height; $y++) {
            $line = str_repeat($this->light, $this->quietZone);
            for ($x = 0; $x < $width; $x++) {
                $val = $matrix[$y][$x];
                $isDark = ($val === true || $val === 1 || (is_int($val) && $val > 0 && ($val & 0x0400) > 0 || ($val & 0x0800) > 0 || ($val & 0x01) > 0));
                $line .= $isDark ? $this->dark : $this->light;
            }
            $line .= str_repeat($this->light, $this->quietZone);
            $lines[] = $line;
        }

        // Bottom quiet zone
        for ($i = 0; $i < $this->quietZone; $i++) {
            $lines[] = str_repeat($this->light, $paddedWidth);
        }

        return implode(PHP_EOL, $lines);
    }
}
