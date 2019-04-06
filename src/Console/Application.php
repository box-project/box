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
use function strpos;
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

    private $releaseDate;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $name = 'Box', ?string $version = null, string $releaseDate = '@release-date@')
    {
        $version = $version ?? get_box_version();

        $this->releaseDate = false === strpos($releaseDate, '@') ? $releaseDate : '';

        parent::__construct($name, $version);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function getHelp(): string
    {
        return self::LOGO.parent::getHelp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new Command\Build();
        $commands[] = new Command\Compile();
        $commands[] = new Command\Diff();
        $commands[] = new Command\Info();
        $commands[] = new Command\Process();
        $commands[] = new Command\Extract();
        $commands[] = new Command\Validate();
        $commands[] = new Command\Verify();
        $commands[] = new Command\GenerateDockerFile();

        return $commands;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultHelperSet(): HelperSet
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new ConfigurationHelper());

        return $helperSet;
    }
}
