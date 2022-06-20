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

use Fidry\Console\Application\Application as FidryApplication;
use function KevinGH\Box\get_box_version;
use function sprintf;
use function trim;

/**
 * @private
 */
final class Application implements FidryApplication
{
    private string $version;
    private string $releaseDate;
    private bool $autoExit;
    private bool $catchExceptions;

    public function __construct(
        private string $name = 'Box',
        ?string $version = null,
        string $releaseDate = '@release-date@',
        bool $autoExit = true,
        bool $catchExceptions = true,
    ) {
        $this->version = $version ?? get_box_version();
        $this->releaseDate = !str_contains($releaseDate, '@') ? $releaseDate : '';
        $this->autoExit = $autoExit;
        $this->catchExceptions = $catchExceptions;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getLongVersion(): string
    {
        return trim(
            sprintf(
                '<info>%s</info> version <comment>%s</comment> %s',
                $this->getName(),
                $this->getVersion(),
                $this->releaseDate,
            ),
        );
    }

    public function getHelp(): string
    {
        return Logo::LOGO_ASCII.$this->getLongVersion();
    }

    public function getCommands(): array
    {
        return [
            new Command\Compile(),
            new Command\Diff(),
            new Command\Info(),
            new Command\Process(),
            new Command\Extract(),
            new Command\Validate(),
            new Command\Verify(),
            new Command\GenerateDockerFile(),
            new Command\Namespace_(),
        ];
    }

    public function getDefaultCommand(): string
    {
        return 'list';
    }

    public function isAutoExitEnabled(): bool
    {
        return $this->autoExit;
    }

    public function areExceptionsCaught(): bool
    {
        return $this->catchExceptions;
    }
}
