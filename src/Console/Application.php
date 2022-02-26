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
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Helper\HelperSet;
use function trim;
use KevinGH\Box\Console\Command;

/**
 * @private
 */
final class Application implements FidryApplication
{
    private const LOGO = <<<'ASCII'

            ____
           / __ )____  _  __
          / __  / __ \| |/_/
         / /_/ / /_/ />  <
        /_____/\____/_/|_|



        ASCII;

    private string $version;
    private string $releaseDate;

    public function __construct(
        private string $name = 'Box',
        ?string $version = null,
        string $releaseDate = '@release-date@'
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
        return self::LOGO.$this->getLongVersion();
    }

    public function getCommands(): array
    {
        return [
//            new Command\Compile(),
//            new Command\Diff(),
//            new Command\Info(),
//            new Command\Process(),
            new Command\Extract(),
//            new Command\Validate(),
            new Command\Verify(),
//            new Command\GenerateDockerFile(),
            new Command\Namespace_(),
        ];
    }

    public function getDefaultCommand(): string
    {
        return 'list';
    }

    public function isAutoExitEnabled(): bool
    {
        return true;
    }

    public function areExceptionsCaught(): bool
    {
        return true;
    }
}
