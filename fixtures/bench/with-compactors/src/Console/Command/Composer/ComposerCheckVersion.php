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

namespace BenchTest\Console\Command\Composer;

use BenchTest\Composer\ComposerOrchestrator;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;

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
            'Checks if the Composer executable used is compatible with Box',
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
        $composerOrchestrator->checkVersion();

        return ExitCode::SUCCESS;
    }
}
