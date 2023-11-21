<?php

declare(strict_types=1);

namespace KevinGH\Box\Console;

use Fidry\Console\IO;
use KevinGH\Box\Console\Php\PhpSettingsHandler;
use KevinGH\Box\NotInstantiable;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * @internal
 */
final class PhpSettingsChecker
{
    use NotInstantiable;

    public static function check(IO $io): void
    {
        (new PhpSettingsHandler(
            new ConsoleLogger(
                $io->getOutput(),
            ),
        ))->check();
    }
}