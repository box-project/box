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

use function array_shift;
use function count;
use function explode;
use function ltrim;
use function min;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function trim;
use function wordwrap;
use const PHP_EOL;

/**
 * @private
 */
final class Printer
{
    private $styles = [
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'title' => "\033[33m",
        'error' => "\033[37;41m",
        'success' => "\033[30;42m",
    ];
    private $verbosity;
    private $supportColors;
    private $width;

    public function __construct(int $verbosity, bool $supportColors, ?int $width = null)
    {
        if (null === $width) {
            $terminal = new Terminal();
            $width = $terminal->getWidth();
        }

        $this->verbosity = $verbosity;
        $this->supportColors = $supportColors;
        $this->width = $width ?: 80;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function setVerbosity($verbosity): void
    {
        $this->verbosity = $verbosity;
    }

    public function title(string $title, int $verbosity, ?string $style = null): void
    {
        if (null === $style) {
            $style = 'title';
        }

        $this->printvln('', $verbosity, $style);
        $this->printvln($title, $verbosity, $style);
        $this->printvln(
            str_repeat(
                '=',
                min(strlen($title), $this->width)
            ),
            $verbosity,
            $style
        );
        $this->printvln('', $verbosity, $style);
    }

    public function getRequirementErrorMessage(Requirement $requirement): ?string
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        return wordwrap($requirement->getHelpText(), $this->width - 3, PHP_EOL.'   ').PHP_EOL;
    }

    public function block(string $title, string $message, int $verbosity, ?string $style = null): void
    {
        $prefix = ' ['.$title.'] ';
        $lineLength = $this->width - strlen($prefix) - 1;
        if ($lineLength < 0) {
            $lineLength = 0;
        }
        $message = $prefix.trim($message);

        $lines = [];

        $remainingMessage = $message;

        $wrapped = wordwrap($remainingMessage, $lineLength, '¬');
        $wrapped = explode('¬', $wrapped);

        do {
            $line = array_shift($wrapped);
            if ($lines && $lineLength > 0) {
                $line = str_repeat(' ', strlen($prefix)).ltrim($line);
            }
            $lines[] = str_pad($line, $this->width, ' ', STR_PAD_RIGHT);
        } while (count($wrapped));

        $this->printvln('', $verbosity);
        $this->printvln(str_repeat(' ', $this->width), $verbosity, $style);
        foreach ($lines as $line) {
            $this->printvln($line, $verbosity, $style);
        }
        $this->printv(str_repeat(' ', $this->width), $verbosity, $style);
        $this->printvln('', $verbosity);
    }

    public function printvln(string $message, int $verbosity, ?string $style = null): void
    {
        $this->printv($message, $verbosity, $style);
        $this->printv(PHP_EOL, $verbosity, null);
    }

    public function printv(string $message, int $verbosity, ?string $style = null): void
    {
        if ($verbosity > $this->verbosity) {
            return;
        }

        $message = wordwrap($message, $this->width);

        $message = sprintf(
            '%s%s%s',
            $this->supportColors && isset($this->styles[$style]) ? $this->styles[$style] : '',
            $message,
            $this->supportColors ? $this->styles['reset'] : ''
        );

        if ('1' === getenv('BOX_REQUIREMENTS_CHECKER_LOG_TO_STDOUT')) {
            // use echo/print to support output buffering
            echo $message;
        } else {
            fwrite(STDERR, $message);
        }
    }
}
