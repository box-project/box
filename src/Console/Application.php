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

use function KevinGH\Box\get_box_version;
use function sprintf;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Helper\HelperSet;
use function trim;

/**
 * @private
 */
final class Application extends SymfonyApplication
{
    private const LOGO = <<<'ASCII'

            ____
           / __ )____  _  __
          / __  / __ \| |/_/
         / /_/ / /_/ />  <
        /_____/\____/_/|_|



        ASCII;

    private string $releaseDate;

    public function __construct(string $name = 'Box', ?string $version = null, string $releaseDate = '@release-date@')
    {
        $version ??= get_box_version();

        $this->releaseDate = !str_contains($releaseDate, '@') ? $releaseDate : '';

        parent::__construct($name, $version);
    }

    public function getLongVersion(): string
    {
        return trim(
            sprintf(
                '<info>%s</info> version <comment>%s</comment> %s',
                $this->getName(),
                $this->getVersion(),
                $this->releaseDate
            )
        );
    }

    public function getHelp(): string
    {
        return self::LOGO.parent::getHelp();
    }

    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        // TODO: re-order the commands?
        $commands[] = new Command\Compile();
        $commands[] = new Command\Diff();
        $commands[] = new Command\Info();
        $commands[] = new Command\Process();
        $commands[] = new Command\Extract();
        $commands[] = new Command\Validate();
        $commands[] = new Command\Verify();
        $commands[] = new Command\GenerateDockerFile();
        $commands[] = new Command\Namespace_();

        return $commands;
    }

    protected function getDefaultHelperSet(): HelperSet
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new ConfigurationHelper());

        return $helperSet;
    }
}
