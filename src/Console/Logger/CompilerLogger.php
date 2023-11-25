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

namespace KevinGH\Box\Console\Logger;

use Fidry\Console\IO;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

/**
 * @internal
 */
final readonly class CompilerLogger
{
    public const QUESTION_MARK_PREFIX = '?';
    public const STAR_PREFIX = '*';
    public const PLUS_PREFIX = '+';
    public const MINUS_PREFIX = '-';
    public const CHEVRON_PREFIX = '>';

    public function __construct(private IO $io)
    {
    }

    public function getIO(): IO
    {
        return $this->io;
    }

    public function log(string $prefix, string $message, int $verbosity = OutputInterface::OUTPUT_NORMAL): void
    {
        $prefix = match ($prefix) {
            '!' => "<error>{$prefix}</error>",
            self::STAR_PREFIX => "<info>{$prefix}</info>",
            self::QUESTION_MARK_PREFIX => "<comment>{$prefix}</comment>",
            self::PLUS_PREFIX, self::MINUS_PREFIX => "  <comment>{$prefix}</comment>",
            self::CHEVRON_PREFIX => "    <comment>{$prefix}</comment>",
            default => throw new InvalidArgumentException('Expected one of the logger constant as a prefix.'),
        };

        $this->io->writeln(
            "{$prefix} {$message}",
            $verbosity,
        );
    }

    public function logStartBuilding(string $path): void
    {
        $this->io->writeln(
            sprintf(
                '🔨  Building the PHAR "<comment>%s</comment>"',
                $path,
            ),
        );
        $this->io->newLine();
    }
}
