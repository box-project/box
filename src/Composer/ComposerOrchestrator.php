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
use Humbug\PhpScoper\Autoload\ScoperAutoloadGenerator;
use Humbug\PhpScoper\Configuration as PhpScoperConfiguration;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use function KevinGH\Box\FileSystem\dump_file;

final class ComposerOrchestrator
{
    private function __construct()
    {
    }

    public static function dumpAutoload(?PhpScoperConfiguration $configuration): void
    {
        // TODO: we are running Composer a first time to assign ComposerApplication#io. However it should be possible
        // to do without by patching Composer at the core to construct the application with a NullIO
        $composerApplication = new ComposerApplication();
        $composerApplication->doRun(new ArrayInput(['--no-plugins' => null]), new NullOutput());

        $composer = $composerApplication->getComposer(false, true);

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

        if (null !== $configuration) {
            // TODO: make prefix configurable
            $autoload = (new ScoperAutoloadGenerator($configuration->getWhitelist()))->dump('_HumbugBox');

            // TODO: handle custom vendor dir
            // TODO: expose the scoper autoload file name via a constant
            dump_file('vendor/scoper-autoload.php', $autoload);
        }

        $generator->dump($config, $localRepo, $package, $installationManager, 'composer', true);
    }
}
