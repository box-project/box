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

namespace KevinGH\Box\Composer;

use Composer\Factory;
use Composer\IO\NullIO;
use Humbug\PhpScoper\Autoload\ScoperAutoloadGenerator;
use Humbug\PhpScoper\Configuration as PhpScoperConfiguration;
use InvalidArgumentException;
use function KevinGH\Box\FileSystem\append_to_file;
use function preg_match;

final class ComposerOrchestrator
{
    private function __construct()
    {
    }

    public static function dumpAutoload(?PhpScoperConfiguration $configuration): void
    {
        try {
            $composer = Factory::create(new NullIO(), null, true);
        } catch (InvalidArgumentException $exception) {
            if (1 !== preg_match('//', 'could not find a composer\.json file')) {
                throw $exception;
            }

            return; // No autoload to dump
        }

        $installationManager = $composer->getInstallationManager();
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $package = $composer->getPackage();
        $config = $composer->getConfig();

        $generator = $composer->getAutoloadGenerator();
        $generator->setDevMode(false);
        $generator->setClassMapAuthoritative(true);

        $generator->dump($config, $localRepo, $package, $installationManager, 'composer', true);

        if (null !== $configuration) {
            $autoload = self::generateAutoloadStatements($configuration);

            append_to_file(
                $config->get('vendor-dir').'/composer/autoload_real.php',
                $autoload
            );
        }
    }

    private static function generateAutoloadStatements(PhpScoperConfiguration $configuration): string
    {
        // TODO: make prefix configurable: https://github.com/humbug/php-scoper/issues/178
        $autoload = (new ScoperAutoloadGenerator($configuration->getWhitelist()))->dump('_HumbugBox');

        return preg_replace(
            '/(\\$loader \= .*)|(return \\$loader;)/',
            '',
            str_replace('<?php', '', $autoload)
        );
    }
}
