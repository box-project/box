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
use KevinGH\Box\Composer\ComposerOrchestrator;
use KevinGH\Box\Console\Php\PhpSettingsHandler;
use function class_exists;
use function ini_get;
use function KevinGH\Box\check_php_settings;

/**
 * @private
 */
final class ComposerCheckVersion extends ComposerCommand
{
    public function getConfiguration(): Configuration
    {
        $parentConfig = parent::getConfiguration();

        return new Configuration(
            'composer:check-version',
            '🎵  Checks if the Composer executable used is compatible with Box',
            <<<'HELP'
                The <info>%command.name%</info> command will look for the Composer binary (in the system if not configured
                in the configuration file) and check if its version is compatible with Box.
                HELP,
            $parentConfig->getArguments(),
            $parentConfig->getOptions(),
        );
    }

    protected function orchestrate(ComposerOrchestrator $composerOrchestrator, IO $io): int
    {
        check_php_settings($io);
        if (!class_exists('Phar')) {
            die('no PHAR class');
        }

        $composerOrchestrator->checkVersion();



        return ExitCode::SUCCESS;
    }
}
