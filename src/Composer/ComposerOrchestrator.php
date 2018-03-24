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
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function preg_match;
use function preg_replace;

final class ComposerOrchestrator
{
    private function __construct()
    {
    }

    public static function dumpAutoload(?PhpScoperConfiguration $phpScoperConfig): void
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
        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $package = $composer->getPackage();
        $composerConfig = $composer->getConfig();

        $generator = $composer->getAutoloadGenerator();
        $generator->setDevMode(false);
        $generator->setClassMapAuthoritative(true);

        $generator->dump($composerConfig, $localRepository, $package, $installationManager, 'composer', true);

        if (null !== $phpScoperConfig) {
            $autoloadFile = $composerConfig->get('vendor-dir').'/autoload.php';

            $autoloadContents = self::generateAutoloadStatements(
                $phpScoperConfig,
                file_contents($autoloadFile)
            );

            dump_file($autoloadFile, $autoloadContents);
        }
    }

    private static function generateAutoloadStatements(PhpScoperConfiguration $config, string $autoload): string
    {
        // TODO: make prefix configurable: https://github.com/humbug/php-scoper/issues/178
        $whitelistStatements = (new ScoperAutoloadGenerator($config->getWhitelist()))->dump('_HumbugBox');

        $whitelistStatements = preg_replace(
            '/(\\$loader \= .*)|(return \\$loader;)/',
            '',
            str_replace('<?php', '', $whitelistStatements)
        );

        return preg_replace(
            '/return (ComposerAutoloaderInit.+::getLoader\(\));/',
            <<<PHP
\$loader = \$1;

$whitelistStatements

return \$loader;
PHP
            ,
            $autoload
        );
    }
}
