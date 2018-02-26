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

use Composer\Console\Application as ComposerApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ComposerOrchestrator
{
    private function __construct()
    {
    }

    public static function dumpAutoload(): void
    {
        $composerApplication = new ComposerApplication();
        $composerApplication->doRun(new ArrayInput([]), new NullOutput());

        $composer = $composerApplication->getComposer(false);

        if (null === $composer) {
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
    }
}
