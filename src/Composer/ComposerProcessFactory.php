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

namespace KevinGH\Box\Composer;

use Fidry\Console\Input\IO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use const KevinGH\Box\BOX_ALLOW_XDEBUG;

/**
 * @private
 */
final class ComposerProcessFactory
{
    public static function create(
        ?string $composerExecutable = null,
        ?IO $io = null,
    ): self {
        $io ??= IO::createNull();

        return new self(
            $composerExecutable ?? self::retrieveComposerExecutable(),
            self::retrieveSubProcessVerbosity($io),
            $io->isDecorated(),
        );
    }

    public function __construct(
        public readonly string $composerExecutable,
        private ?string $verbosity,
        private bool $ansi,
    ) {
    }

    public function getVersionProcess(): Process
    {
        // Never use ANSI support here as we want to parse the raw output.
        return new Process([
            $this->composerExecutable,
            '--version',
            '--no-ansi',
        ]);
    }

    public function getDumpAutoloaderProcess(bool $noDev): Process
    {
        $composerCommand = [$this->composerExecutable, 'dump-autoload', '--classmap-authoritative'];

        if (true === $noDev) {
            $composerCommand[] = '--no-dev';
        }

        if (null !== $this->verbosity) {
            $composerCommand[] = $this->verbosity;
        }

        if ($this->ansi) {
            $composerCommand[] = '--ansi';
        }

        return new Process($composerCommand);
    }

    public function getAutoloadFileProcess(): Process
    {
        return new Process([
            $this->composerExecutable,
            'config',
            'vendor-dir',
            '--no-ansi',
        ]);
    }

    private static function retrieveSubProcessVerbosity(IO $io): ?string
    {
        if ($io->isDebug()) {
            return '-vvv';
        }

        if ($io->isVeryVerbose()) {
            return '-v';
        }

        return null;
    }

    public function getDefaultEnvVars(): array
    {
        $vars = [];

        if ('1' === (string) getenv(BOX_ALLOW_XDEBUG)) {
            $vars['COMPOSER_ALLOW_XDEBUG'] = '1';
        }

        return $vars;
    }

    private static function retrieveComposerExecutable(): string
    {
        $executableFinder = new ExecutableFinder();
        $executableFinder->addSuffix('.phar');

        if (null === $composer = $executableFinder->find('composer')) {
            throw new RuntimeException('Could not find a Composer executable.');
        }

        return $composer;
    }
}
