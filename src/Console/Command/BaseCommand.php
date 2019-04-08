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

namespace KevinGH\Box\Console\Command;

use KevinGH\Box\Console\Application;
use KevinGH\Box\Console\ConfigurationHelper;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Console\OutputFormatterConfigurator;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tiny Symfony Command adapter to allow the command to easily access to the typehinted helpers which are configured
 * in the application.
 *
 * @see Application
 */
abstract class BaseCommand extends SymfonyCommand
{
    abstract protected function executeCommand(IO $io): int;

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        OutputFormatterConfigurator::configure($output);

        return $this->executeCommand(new IO($input, $output));
    }

    final protected function getConfigurationHelper(): ConfigurationHelper
    {
        return $this->getHelper('config');
    }
}
