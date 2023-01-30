<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\RequirementChecker;

use function fstat;
use function function_exists;
use function getenv;
use function implode;
use function posix_isatty;
use function preg_match;
use function preg_quote;
use function sapi_windows_vt100_support;
use function sprintf;
use function str_replace;
use function stream_isatty;
use const DIRECTORY_SEPARATOR;
use const STDOUT;

/**
 * @private
 */
final class IO
{
    public const VERBOSITY_QUIET = 16;
    public const VERBOSITY_NORMAL = 32;
    public const VERBOSITY_VERBOSE = 64;
    public const VERBOSITY_VERY_VERBOSE = 128;
    public const VERBOSITY_DEBUG = 256;

    private $interactive;
    private $verbosity = self::VERBOSITY_NORMAL;
    private $colorSupport;
    private $options;

    public function __construct()
    {
        $this->options = implode(' ', $_SERVER['argv']);

        $shellVerbosity = $this->configureVerbosity();

        $this->interactive = $this->checkInteractivity($shellVerbosity);
        $this->colorSupport = $this->checkColorSupport();
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function hasColorSupport(): bool
    {
        return $this->colorSupport;
    }

    public function hasParameter($values): bool
    {
        $values = (array) $values;

        foreach ($values as $value) {
            $regexp = sprintf(
                '/\s%s\b/',
                str_replace(' ', '\s+', preg_quote($value, '/'))
            );

            if (1 === preg_match($regexp, $this->options)) {
                return true;
            }
        }

        return false;
    }

    private function checkInteractivity(int $shellVerbosity): bool
    {
        if (-1 === $shellVerbosity) {
            return false;
        }

        if (true === $this->hasParameter(['--no-interaction', '-n'])) {
            return false;
        }

        if (function_exists('posix_isatty')
            && !@posix_isatty(STDOUT)
            && false === getenv('SHELL_INTERACTIVE')
        ) {
            return false;
        }

        return true;
    }

    private function configureVerbosity(): int
    {
        switch ($shellVerbosity = (int) getenv('SHELL_VERBOSITY')) {
            case -1:
                $this->verbosity = self::VERBOSITY_QUIET;
                break;
            case 1:
                $this->verbosity = self::VERBOSITY_VERBOSE;
                break;
            case 2:
                $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
                break;
            case 3:
                $this->verbosity = self::VERBOSITY_DEBUG;
                break;
            default:
                $shellVerbosity = 0;
                break;
        }

        if ($this->hasParameter(['--quiet', '-q'])) {
            $this->verbosity = self::VERBOSITY_QUIET;
            $shellVerbosity = -1;
        } elseif ($this->hasParameter(['-vvv', '--verbose=3', '--verbose 3'])) {
            $this->verbosity = self::VERBOSITY_DEBUG;
            $shellVerbosity = 3;
        } elseif ($this->hasParameter(['-vv', '--verbose=2', '--verbose 2'])) {
            $this->verbosity = self::VERBOSITY_VERY_VERBOSE;
            $shellVerbosity = 2;
        } elseif ($this->hasParameter(['-v', '--verbose=1', '--verbose 1', '--verbose'])) {
            $this->verbosity = self::VERBOSITY_VERBOSE;
            $shellVerbosity = 1;
        }

        return $shellVerbosity;
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * Colorization is disabled if not supported by the stream:
     *
     *  -  Windows != 10.0.10586 without Ansicon, ConEmu or Mintty
     *  -  non tty consoles
     *
     * @return bool true if the stream supports colorization, false otherwise
     *
     * @see \Symfony\Component\Console\Output\StreamOutput
     *
     * @license MIT (c) Fabien Potencier <fabien@symfony.com>
     */
    private function checkColorSupport(): bool
    {
        if ($this->hasParameter(['--ansi'])) {
            return true;
        }

        if ($this->hasParameter(['--no-ansi'])) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return (
                function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT)
            )
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        $stat = fstat(STDOUT);

        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }
}
