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

use Fidry\Console\IO;
use InvalidArgumentException;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Configuration\ConfigurationLoader as ConfigLoader;
use KevinGH\Box\Configuration\NoConfigurationFound;
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
     * @throws JsonValidationException|NoConfigurationFound
     */
    public static function getConfig(
        ?string $configPath,
        IO $io,
        bool $allowNoFile,
    ): Configuration {
        $configPath = self::getConfigPath($configPath, $io, $allowNoFile);
        $configLoader = new ConfigLoader();

        try {
            return $configLoader->loadFile($configPath);
        } catch (InvalidArgumentException $invalidConfig) {
            $io->error('The configuration file is invalid.');

            throw $invalidConfig;
        }
    }

    private static function getConfigPath(
        ?string $configPath,
        IO $io,
        bool $allowNoFile,
    ): ?string {
        try {
            $configPath ??= ConfigurationLocator::findDefaultPath();
        } catch (NoConfigurationFound $noConfigurationFound) {
            if (false === $allowNoFile) {
                throw $noConfigurationFound;
            }

            $io->comment('Loading without a configuration file.');

            return null;
        }

        $io->comment(
            sprintf(
                'Loading the configuration file "<comment>%s</comment>".',
                $configPath,
            ),
        );

        return $configPath;
    }
}
