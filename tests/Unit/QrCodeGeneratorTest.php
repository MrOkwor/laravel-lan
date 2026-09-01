<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Tests\Unit;

use Mrokwor\LaravelLan\QR\QrCodeGenerator;
use Mrokwor\LaravelLan\QR\Renderers\PlainTextRenderer;
use Mrokwor\LaravelLan\QR\Renderers\TerminalBlockRenderer;
use PHPUnit\Framework\TestCase;

final class QrCodeGeneratorTest extends TestCase
{
    public function test_generates_terminal_block_qr_code(): void
    {
        $generator = new QrCodeGenerator(new TerminalBlockRenderer());
        $output = $generator->generate('http://192.168.1.42:8000');

        $this->assertNotNull($output);
        $this->assertNotEmpty($output);
        $this->assertStringContainsString("\u{2588}", $output); // Contains UTF-8 block chars
    }

    public function test_generates_ascii_plain_text_qr_code(): void
    {
        $generator = new QrCodeGenerator(new PlainTextRenderer());
        $output = $generator->generate('http://192.168.1.42:8000');

        $this->assertNotNull($output);
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('##', $output); // Contains ASCII hash marks
    }

    public function test_handles_empty_url_gracefully(): void
    {
        $generator = new QrCodeGenerator();
        $this->assertNull($generator->generate(''));
    }
}
