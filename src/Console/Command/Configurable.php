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

use InvalidArgumentException;
use KevinGH\Box\Configuration;
use KevinGH\Box\Throwable\Exception\NoConfigurationFound;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Allows a configuration file path to be specified for a command.
 */
abstract class Configurable extends Command
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param bool            $allowNoFile Load the config nonetheless if not file is found when true
     *
     * @return Configuration
     */
    final protected function getConfig(InputInterface $input, OutputInterface $output, bool $allowNoFile = false): Configuration
    {
        /** @var $helper \KevinGH\Box\Console\ConfigurationHelper */
        $helper = $this->getHelper('config');

        $io = new SymfonyStyle($input, $output);

        try {
            $configPath = null !== $input->getOption(self::CONFIG_PARAM)
                ? $input->getOption(self::CONFIG_PARAM)
                : $helper->findDefaultPath()
            ;

            $io->comment(
                sprintf(
                    'Loading the configuration file "<comment>%s</comment>".',
                    $configPath
                )
            );
        } catch (NoConfigurationFound $exception) {
            if (false === $allowNoFile) {
                throw $exception;
            }

            $configPath = null;
        }

        try {
            return $helper->loadFile($configPath);
        } catch (InvalidArgumentException $exception) {
            $io->error('The configuration file is invalid.');

            throw $exception;
        }
    }
}
