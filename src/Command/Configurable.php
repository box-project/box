<?php

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
    protected function configure()
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
     * @param InputInterface $input The input handler.
     *
     * @return Configuration The configuration settings.
     */
    protected function getConfig(InputInterface $input)
    {
        /** @var $helper ConfigurationHelper */
        $helper = $this->getHelper('config');

        return $helper->loadFile($input->getOption('configuration'));
    }
}
