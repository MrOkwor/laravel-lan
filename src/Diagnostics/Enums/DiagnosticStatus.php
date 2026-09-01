<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics\Enums;

enum DiagnosticStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
    case Info = 'info';

    public function symbol(): string
    {
        return match ($this) {
            self::Pass => '✓',
            self::Warning => '⚠',
            self::Fail => '✗',
            self::Info => 'ℹ',
        };
    }

    public function tag(): string
    {
        return match ($this) {
            self::Pass => 'info',
            self::Warning => 'comment',
            self::Fail => 'error',
            self::Info => 'question',
        };
    }
}
