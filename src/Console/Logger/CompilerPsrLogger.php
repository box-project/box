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

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\Console\Output\OutputInterface;
use function array_key_exists;

final class CompilerPsrLogger extends AbstractLogger
{
    public function __construct(
        private readonly CompilerLogger $decoratedLogger,
    ) {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $verbosity = self::getVerbosity($level);
        $output = self::getOutput($context);

        if (null === $output) {
            $this->decoratedLogger->log(
                CompilerLogger::CHEVRON_PREFIX,
                $message,
                $verbosity,
            );
        } else {
            $this->decoratedLogger->getIO()->writeln(
                $output,
                $verbosity,
            );
        }
    }

    private static function getVerbosity(string $level): int
    {
        return match ($level) {
            LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_VERY_VERBOSE,
            default => OutputInterface::OUTPUT_NORMAL,
        };
    }

    private static function getOutput(array $context): ?string
    {
        $knownKeys = ['stdout', 'stderr'];

        foreach ($knownKeys as $knownKey) {
            if (array_key_exists($knownKey, $context)) {
                return $context[$knownKey];
            }
        }

        return null;
    }
}
