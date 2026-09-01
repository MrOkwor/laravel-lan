<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics;

use Mrokwor\LaravelLan\Diagnostics\Enums\DiagnosticStatus;

final readonly class DiagnosticResult
{
    public function __construct(
        public string $name,
        public DiagnosticStatus $status,
        public string $message,
        public ?string $hint = null,
        public array $data = []
    ) {
    }

    public static function pass(string $name, string $message, array $data = []): self
    {
        return new self($name, DiagnosticStatus::Pass, $message, null, $data);
    }

    public static function warning(string $name, string $message, ?string $hint = null, array $data = []): self
    {
        return new self($name, DiagnosticStatus::Warning, $message, $hint, $data);
    }

    public static function fail(string $name, string $message, ?string $hint = null, array $data = []): self
    {
        return new self($name, DiagnosticStatus::Fail, $message, $hint, $data);
    }

    public static function info(string $name, string $message, ?string $hint = null, array $data = []): self
    {
        return new self($name, DiagnosticStatus::Info, $message, $hint, $data);
    }

    public function isSuccess(): bool
    {
        return $this->status === DiagnosticStatus::Pass || $this->status === DiagnosticStatus::Info;
    }

    public function isBlocking(): bool
    {
        return $this->status === DiagnosticStatus::Fail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'message' => $this->message,
            'hint' => $this->hint,
            'data' => $this->data,
        ];
    }
}
