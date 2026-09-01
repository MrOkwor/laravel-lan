<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Support;

final class Platform
{
    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function isMac(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public static function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public static function getName(): string
    {
        return PHP_OS_FAMILY;
    }

    public static function supportsUtf8(): bool
    {
        if (self::isWindows()) {
            // Windows Terminal (WT_SESSION) or ConEmu or VS Code terminal support UTF-8 well
            return getenv('WT_SESSION') !== false
                || getenv('VSCODE_PID') !== false
                || getenv('TERM_PROGRAM') !== false
                || getenv('ConEmuPID') !== false
                || (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT));
        }

        $lang = getenv('LANG') ?: '';
        $lcAll = getenv('LC_ALL') ?: '';

        return stripos($lang, 'utf-8') !== false
            || stripos($lang, 'utf8') !== false
            || stripos($lcAll, 'utf-8') !== false
            || stripos($lcAll, 'utf8') !== false;
    }

    public static function supportsAnsi(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT))
                || getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('WT_SESSION') !== false
                || getenv('TERM_PROGRAM') !== false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDOUT);
        }

        return true;
    }
}
