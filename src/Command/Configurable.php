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

namespace KevinGH\Box\Command;

use KevinGH\Box\Configuration;
use KevinGH\Box\Helper\ConfigurationHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Allows a configuration file path to be specified for a command.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
abstract class Configurable extends AbstractCommand
{
    /**
     * @override
     */
    protected function configure(): void
    {
        $this->addOption(
            'configuration',
            'c',
            InputOption::VALUE_REQUIRED,
            'The alternative configuration file path.'
        );
    }

    /**
     * Returns the configuration settings.
     *
     * @param InputInterface $input the input handler
     *
     * @return Configuration the configuration settings
     */
    protected function getConfig(InputInterface $input)
    {
        /** @var $helper ConfigurationHelper */
        $helper = $this->getHelper('config');

        return $helper->loadFile($input->getOption('configuration'));
    }
}
