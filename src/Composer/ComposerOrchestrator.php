<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer;

use Composer\Console\Application as ComposerApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ComposerOrchestrator
{
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

    private function __construct()
    {
    }
}