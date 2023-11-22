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

namespace KevinGH\Box\Console;

use Closure;
use Fidry\Console\IO;
use KevinGH\Box\Noop;
use KevinGH\Box\NotInstantiable;
use Symfony\Component\Console\Output\OutputInterface;
use function function_exists;
use function posix_getrlimit;
use function posix_setrlimit;
use function sprintf;
use const POSIX_RLIMIT_INFINITY;
use const POSIX_RLIMIT_NOFILE;

/**
 * @internal
 */
final class OpenFileDescriptorLimiter
{
    use NotInstantiable;

    private const LIMIT_MARGIN = 128;

    /**
     * Bumps the maximum number of open file descriptor if necessary.
     *
     * @return Closure Callable to call to restore the original maximum number of open files descriptors
     */
    public static function bumpLimit(int $count, IO $io): Closure
    {
        $count += self::LIMIT_MARGIN;  // Add a little extra for good measure

        if (false === function_exists('posix_getrlimit') || false === function_exists('posix_setrlimit')) {
            $io->writeln(
                '<info>[debug] Could not check the maximum number of open file descriptors: the functions "posix_getrlimit()" and '
                .'"posix_setrlimit" could not be found.</info>',
                OutputInterface::VERBOSITY_DEBUG,
            );

            return Noop::create();
        }

        $softLimit = posix_getrlimit()['soft openfiles'];
        $hardLimit = posix_getrlimit()['hard openfiles'];

        if ($softLimit >= $count) {
            return Noop::create();
        }

        $io->writeln(
            sprintf(
                '<info>[debug] Increased the maximum number of open file descriptors from ("%s", "%s") to ("%s", "%s")'
                .'</info>',
                $softLimit,
                $hardLimit,
                $count,
                'unlimited',
            ),
            OutputInterface::VERBOSITY_DEBUG,
        );

        posix_setrlimit(
            POSIX_RLIMIT_NOFILE,
            $count,
            'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit,
        );

        return static function () use ($io, $softLimit, $hardLimit): void {
            posix_setrlimit(
                POSIX_RLIMIT_NOFILE,
                $softLimit,
                'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit,
            );

            $io->writeln(
                '<info>[debug] Restored the maximum number of open file descriptors</info>',
                OutputInterface::VERBOSITY_DEBUG,
            );
        };
    }
}
