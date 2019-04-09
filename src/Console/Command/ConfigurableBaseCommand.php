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

use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\ConfigurationLoader;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Json\JsonValidationException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Allows a configuration file path to be specified for a command.
 *
 * @private
 */
abstract class ConfigurableBaseCommand extends BaseCommand
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
    final protected function getConfig(IO $io, bool $allowNoFile = false): Configuration
    {
        return ConfigurationLoader::getConfig(
            $io->getInput()->getOption(self::CONFIG_PARAM),
            $this->getConfigurationHelper(),
            $io,
            $allowNoFile
        );
    }

    final protected function getConfigPath(InputInterface $input): string
    {
        return $input->getOption(self::CONFIG_PARAM) ?? $this->getConfigurationHelper()->findDefaultPath();
    }
}
