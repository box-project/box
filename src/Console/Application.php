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

use Humbug\SelfUpdate\Exception\RuntimeException as SelfUpdateRuntimeException;
use Humbug\SelfUpdate\Updater;
use KevinGH\Box\Console\Command\SelfUpdateCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Helper\HelperSet;

final class Application extends SymfonyApplication
{
    private const LOGO = <<<'ASCII'

    ____            
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <  
/_____/\____/_/|_|  
                    


ASCII;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $name = 'Box', string $version = '@git-version@')
    {
        parent::__construct($name, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function getLongVersion()
    {
        if (('@'.'git-version@') !== $this->getVersion()) {
            return sprintf(
                '<info>%s</info> version <comment>%s</comment> build <comment>%s</comment>',
                $this->getName(),
                $this->getVersion(),
                '@git-commit@'
            );
        }

        return '<info>'.$this->getName().'</info> (repo)';
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
        $commands[] = new Command\Info();
        $commands[] = new Command\Validate();
        $commands[] = new Command\Verify();

        if ('phar:' === substr(__FILE__, 0, 5)) {
            try {
                $updater = new Updater();
            } catch (SelfUpdateRuntimeException $e) {
                // Allow E2E testing of unsigned phar
                $updater = new Updater(null, false);
            }
            $commands[] = new SelfUpdateCommand($updater);
        }

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
