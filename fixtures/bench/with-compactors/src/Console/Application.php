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

namespace BenchTest\Console;

use Fidry\Console\Application\Application as FidryApplication;
use function BenchTest\get_box_version;
use function sprintf;
use function trim;

/**
 * @private
 */
final class Application implements FidryApplication
{
    private string $version;
    private string $releaseDate;
    private string $header;

    public function __construct(
        private string $name = 'Box',
        ?string $version = null,
        string $releaseDate = '@release-date@',
        private bool $autoExit = true,
        private bool $catchExceptions = true,
    ) {
        $this->version = $version ?? get_box_version();
        $this->releaseDate = !str_contains($releaseDate, '@') ? $releaseDate : '';
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
        return $this->getHeader();
    }

    public function getHeader(): string
    {
        if (!isset($this->header)) {
            $this->header = Logo::LOGO_ASCII.$this->getLongVersion();
        }

        return $this->header;
    }

    public function getCommands(): array
    {
        return [
            new Command\Composer\ComposerCheckVersion(),
            new Command\Composer\ComposerVendorDir(),
            new Command\Compile($this->getHeader()),
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
