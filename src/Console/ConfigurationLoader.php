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

use InvalidArgumentException;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Configuration\NoConfigurationFound;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Json\JsonValidationException;
use KevinGH\Box\NotInstantiable;
use function sprintf;

/**
 * Utility to load the configuration.
 *
 * @private
 */
final class ConfigurationLoader
{
    use NotInstantiable;

    /**
     * Returns the configuration settings.
     *
     * @param bool $allowNoFile Load the config nonetheless if not file is found when true
     *
     * @throws JsonValidationException
     */
    public static function getConfig(
        ?string $configPath,
        ConfigurationHelper $helper,
        IO $io,
        bool $allowNoFile
    ): Configuration {
        try {
            $configPath = $configPath ?? $helper->findDefaultPath();

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

            $io->comment('Loading without a configuration file.');

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
