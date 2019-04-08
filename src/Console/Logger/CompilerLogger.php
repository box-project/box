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

use InvalidArgumentException;
use KevinGH\Box\Console\IO\IO;
use function sprintf;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class CompilerLogger
{
    public const QUESTION_MARK_PREFIX = '?';
    public const STAR_PREFIX = '*';
    public const PLUS_PREFIX = '+';
    public const MINUS_PREFIX = '-';
    public const CHEVRON_PREFIX = '>';

    private $io;

    public function __construct(IO $io)
    {
        $this->io = $io;
    }

    public function getIO(): IO
    {
        return $this->io;
    }

    public function log(string $prefix, string $message, int $verbosity = OutputInterface::OUTPUT_NORMAL): void
    {
        switch ($prefix) {
            case '!':
                $prefix = "<error>$prefix</error>";
                break;
            case self::STAR_PREFIX:
                $prefix = "<info>$prefix</info>";
                break;
            case self::QUESTION_MARK_PREFIX:
                $prefix = "<comment>$prefix</comment>";
                break;
            case self::PLUS_PREFIX:
            case self::MINUS_PREFIX:
                $prefix = "  <comment>$prefix</comment>";
                break;
            case self::CHEVRON_PREFIX:
                $prefix = "    <comment>$prefix</comment>";
                break;
            default:
                throw new InvalidArgumentException('Expected one of the logger constant as a prefix.');
        }

        $this->io->writeln(
            "$prefix $message",
            $verbosity
        );
    }

    public function logStartBuilding(string $path): void
    {
        $this->io->writeln(
            sprintf(
                '🔨  Building the PHAR "<comment>%s</comment>"',
                $path
            )
        );
        $this->io->newLine();
    }
}
