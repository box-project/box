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

use KevinGH\Box\Configuration;
use KevinGH\Box\Console\ConfigurationLoader;
use KevinGH\Box\Json\JsonValidationException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Allows a configuration file path to be specified for a command.
 *
 * @private
 */
abstract class ConfigurableCommand extends Command
{
    private const CONFIG_PARAM = 'config';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption(
            self::CONFIG_PARAM,
            'c',
            InputOption::VALUE_REQUIRED,
            'The alternative configuration file path.'
        );
    }

    /**
     * Returns the configuration settings.
     *
     * @param bool $allowNoFile Load the config nonetheless if not file is found when true
     *
     * @throws JsonValidationException
     */
    final protected function getConfig(InputInterface $input, OutputInterface $output, bool $allowNoFile = false): Configuration
    {
        return ConfigurationLoader::getConfig(
            $input->getOption(self::CONFIG_PARAM),
            $this->getConfigurationHelper(),
            new SymfonyStyle($input, $output),
            $allowNoFile
        );
    }

    final protected function getConfigPath(InputInterface $input): string
    {
        return $input->getOption(self::CONFIG_PARAM) ?? $this->getConfigurationHelper()->findDefaultPath();
    }
}
