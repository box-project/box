<?php

declare(strict_types=1);

namespace KevinGH\Box\Console;

use KevinGH\Box\Box;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @private
 */
final class FileDescriptorManipulator
{
    /**
     * Bumps the maximum number of open file descriptor if necessary.
     *
     * @return callable callable to call to restore the original maximum number of open files descriptors
     */
    public static function bumpOpenFileDescriptorLimit(int $filesCount, SymfonyStyle $io): callable
    {
        $filesCount += 128;  // Add a little extra for good measure

        if (false === function_exists('posix_getrlimit') || false === function_exists('posix_setrlimit')) {
            $io->writeln(
                '<info>[debug] Could not check the maximum number of open file descriptors: the functions "posix_getrlimit()" and '
                .'"posix_setrlimit" could not be found.</info>',
                OutputInterface::VERBOSITY_DEBUG
            );

            return static function (): void {};
        }

        $softLimit = posix_getrlimit()['soft openfiles'];
        $hardLimit = posix_getrlimit()['hard openfiles'];

        if ($softLimit >= $filesCount) {
            return static function (): void {};
        }

        $io->writeln(
            sprintf(
                '<info>[debug] Increased the maximum number of open file descriptors from ("%s", "%s") to ("%s", "%s")'
                .'</info>',
                $softLimit,
                $hardLimit,
                $filesCount,
                'unlimited'
            ),
            OutputInterface::VERBOSITY_DEBUG
        );

        posix_setrlimit(
            POSIX_RLIMIT_NOFILE,
            $filesCount,
            'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit
        );

        return static function () use ($io, $softLimit, $hardLimit): void {
            if (function_exists('posix_setrlimit') && isset($softLimit, $hardLimit)) {
                posix_setrlimit(
                    POSIX_RLIMIT_NOFILE,
                    $softLimit,
                    'unlimited' === $hardLimit ? POSIX_RLIMIT_INFINITY : $hardLimit
                );

                $io->writeln(
                    '<info>[debug] Restored the maximum number of open file descriptors</info>',
                    OutputInterface::VERBOSITY_DEBUG
                );
            }
        };
    }

    private function __construct()
    {
    }
}