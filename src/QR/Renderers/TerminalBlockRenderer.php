<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\QR\Renderers;

use Mrokwor\LaravelLan\QR\Contracts\QrCodeRendererInterface;

final class TerminalBlockRenderer implements QrCodeRendererInterface
{
    public function __construct(
        private int $quietZone = 0,
        private bool $useAnsiColors = true,
    ) {
    }

    /**
     * Render matrix using UTF-8 half blocks to achieve square aspect ratio in terminals.
     * Applies high-contrast white background and black foreground styling so phone cameras
     * can scan the QR code immediately on any terminal theme (dark or light).
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
        $paddedHeight = $height + ($this->quietZone * 2);

        // Build binary matrix (true = dark module, false = light background)
        $grid = array_fill(0, $paddedHeight, array_fill(0, $paddedWidth, false));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $val = $matrix[$y][$x];
                $grid[$y + $this->quietZone][$x + $this->quietZone] = (
                    $val === true ||
                    $val === 1 ||
                    (is_int($val) && ($val & 0x0800) === 0x0800)
                );
            }
        }

        $lines = [];

        // Step by 2 rows at a time
        for ($y = 0; $y < $paddedHeight; $y += 2) {
            $line = '';
            for ($x = 0; $x < $paddedWidth; $x++) {
                $top = $grid[$y][$x] ?? false;
                $bottom = $grid[$y + 1][$x] ?? false;

                // With white background and black text:
                // - Top dark & Bottom dark: full black block \u{2588}
                // - Top dark & Bottom light: top half black \u{2580}
                // - Top light & Bottom dark: bottom half black \u{2584}
                // - Top light & Bottom light: full white space " "
                if ($top && $bottom) {
                    $line .= "\u{2588}";
                } elseif ($top && !$bottom) {
                    $line .= "\u{2580}";
                } elseif (!$top && $bottom) {
                    $line .= "\u{2584}";
                } else {
                    $line .= ' ';
                }
            }

            if ($this->useAnsiColors) {
                $lines[] = "<fg=black;bg=bright-white>{$line}</>";
            } else {
                $lines[] = $line;
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
