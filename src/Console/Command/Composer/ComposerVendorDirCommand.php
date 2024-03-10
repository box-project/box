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
use Fidry\Console\IO;
use KevinGH\Box\Composer\ComposerOrchestrator;

/**
 * @private
 */
final class ComposerVendorDirCommand extends ComposerCommand
{
    public function getConfiguration(): Configuration
    {
        $parentConfig = parent::getConfiguration();

        return new Configuration(
            'composer:vendor-dir',
            'Shows the Composer vendor-dir configured',
            <<<'HELP'
                The <info>%command.name%</info> command will look for the Composer binary (in the system if not configured
                in the configuration file) and print the vendor-dir found.
                HELP,
            $parentConfig->getArguments(),
            $parentConfig->getOptions(),
        );
    }

    protected function orchestrate(ComposerOrchestrator $composerOrchestrator, IO $io): int
    {
        $io->writeln($composerOrchestrator->getVendorDir());

        return ExitCode::SUCCESS;
    }
}
