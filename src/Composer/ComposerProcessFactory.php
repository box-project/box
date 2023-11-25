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

use Closure;
use Fidry\Console\IO;
use KevinGH\Box\Constants;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @final
 * @private
 */
class ComposerProcessFactory
{
    private string $composerExecutable;

    public static function create(
        ?string $composerExecutable = null,
        ?IO $io = null,
    ): self {
        $io ??= IO::createNull();

        return new self(
            null === $composerExecutable
                ? self::retrieveComposerExecutable(...)
                : static fn () => $composerExecutable,
            self::retrieveSubProcessVerbosity($io),
            $io->isDecorated(),
            self::getDefaultEnvVars(),
        );
    }

    /**
     * @param Closure():string $composerExecutableFactory
     */
    public function __construct(
        private readonly Closure $composerExecutableFactory,
        private ?string $verbosity,
        private readonly bool $ansi,
        private readonly array $defaultEnvironmentVariables,
    ) {
    }

    public function getVersionProcess(): Process
    {
        return $this->createProcess(
            [
                $this->getComposerExecutable(),
                '--version',
                // Never use ANSI support here as we want to parse the raw output.
                '--no-ansi',
            ],
            // Ensure that even if this command gets executed within the app with --quiet it still
            // works.
            ['SHELL_VERBOSITY' => 0],
        );
    }

    public function getDumpAutoloaderProcess(bool $noDev): Process
    {
        $composerCommand = [$this->getComposerExecutable(), 'dump-autoload', '--classmap-authoritative'];

        if (true === $noDev) {
            $composerCommand[] = '--no-dev';
        }

        if (null !== $this->verbosity) {
            $composerCommand[] = $this->verbosity;
        }

        if ($this->ansi) {
            $composerCommand[] = '--ansi';
        }

        return $this->createProcess($composerCommand);
    }

    public function getVendorDirProcess(): Process
    {
        return $this->createProcess(
            [
                $this->getComposerExecutable(),
                'config',
                'vendor-dir',
                // Never use ANSI support here as we want to parse the raw output.
                '--no-ansi',
            ],
            // Ensure that even if this command gets executed within the app with --quiet it still
            // works.
            ['SHELL_VERBOSITY' => 0],
        );
    }

    private function createProcess(array $command, array $environmentVariables = []): Process
    {
        return new Process(
            $command,
            env: [
                ...$this->defaultEnvironmentVariables,
                ...$environmentVariables,
            ],
        );
    }

    private function getComposerExecutable(): string
    {
        if (!isset($this->composerExecutable)) {
            $this->composerExecutable = ($this->composerExecutableFactory)();
        }

        return $this->composerExecutable;
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

    private static function getDefaultEnvVars(): array
    {
        $vars = ['COMPOSER_ORIGINAL_INIS' => ''];

        if ('1' === (string) getenv(Constants::ALLOW_XDEBUG)) {
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
