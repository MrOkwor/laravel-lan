<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\QR;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Mrokwor\LaravelLan\QR\Contracts\QrCodeRendererInterface;
use Mrokwor\LaravelLan\QR\Renderers\PlainTextRenderer;
use Mrokwor\LaravelLan\QR\Renderers\TerminalBlockRenderer;
use Mrokwor\LaravelLan\Support\Platform;
use Throwable;

final class QrCodeGenerator
{
    public function __construct(
        private ?QrCodeRendererInterface $renderer = null
    ) {
    }

    /**
     * Generate a terminal-renderable QR code for the given URL.
     * Returns null if generation fails or is not supported.
     */
    public function generate(string $url): ?string
    {
        if (empty(trim($url))) {
            return null;
        }

        try {
            if (!class_exists(QRCode::class)) {
                return null;
            }

            $options = new QROptions([
                'version' => Version::AUTO,
                'eccLevel' => EccLevel::M,
                'addQuietzone' => true,
                'quietzoneSize' => 2,
            ]);

            $qrcode = new QRCode($options);
            $qrcode->addByteSegment($url);
            $qrMatrix = $qrcode->getQRMatrix();
            $matrix = $qrMatrix->getMatrix();

            $renderer = $this->renderer ?? $this->getDefaultRenderer();

            return $renderer->render($matrix);
        } catch (Throwable) {
            return null;
        }
    }

    private function getDefaultRenderer(): QrCodeRendererInterface
    {
        if (Platform::supportsUtf8()) {
            return new TerminalBlockRenderer(quietZone: 2);
        }

        return new PlainTextRenderer(quietZone: 2);
    }
}
