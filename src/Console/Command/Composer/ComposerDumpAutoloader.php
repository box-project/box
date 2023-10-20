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

namespace KevinGH\Box\Console\Command\Composer;

use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\Input\IO;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Composer\ComposerOrchestrator;
use KevinGH\Box\Console\Php\PhpSettingsHandler;
use function dd;
use function ini_get;
use function KevinGH\Box\check_php_settings;

/**
 * @private
 */
final class ComposerDumpAutoloader extends ComposerCommand
{
    public function getConfiguration(): Configuration
    {
        $parentConfig = parent::getConfiguration();

        return new Configuration(
            'composer:dump-autoload',
            '🎵  Dumps the autoloader with Composer.',
            <<<'HELP'
                The <info>%command.name%</info> command will look for the Composer binary (in the system if not configured
                in the configuration file) and dump the autoloader.
                HELP,
            $parentConfig->getArguments(),
            $parentConfig->getOptions(),
        );
    }

    protected function orchestrate(ComposerOrchestrator $composerOrchestrator, IO $io): int
    {
        check_php_settings($io);

        if (!class_exists('Phar')) {
            dump(
                PHP_BINARY,
                $args = array_slice($_SERVER['argv'], 1),
                PhpSettingsHandler::getRestartSettings(),
            );

            die('no PHAR class');
        }

        $composerOrchestrator->dumpAutoload(
            new SymbolsRegistry(),
            '',
            false,
        );

        if (!class_exists('Phar')) {
            throw new \Error('no PHAR class');
        }

        return ExitCode::SUCCESS;
    }
}
