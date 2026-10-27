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

use Fidry\FileSystem\FS;
use function getenv;
use function Safe\putenv;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Runs the Composer fixture executables with an empty COMPOSER_HOME. Without it, they
 * read the global auth.json of the machine; in the CI it contains a GitHub token that
 * the old Composer versions of the fixtures reject.
 *
 * TODO: to replace this with the PHPUnit `WithEnvironmentVariable` attribute once
 *   available.
 */
trait IsolatesComposerHome
{
    private string|false $originalComposerHome;
    private string $tmpComposerHome;

    private function isolateComposerHome(): void
    {
        $this->originalComposerHome = getenv('COMPOSER_HOME');
        $this->tmpComposerHome = sys_get_temp_dir().'/box-composer-home-'.uniqid();

        FS::mkdir($this->tmpComposerHome);

        self::setComposerHome($this->tmpComposerHome);
    }

    private function restoreComposerHome(): void
    {
        self::setComposerHome($this->originalComposerHome);

        FS::remove($this->tmpComposerHome);
    }

    /**
     * Symfony Process builds the environment of the child process from $_SERVER and
     * $_ENV, so putenv() alone is not enough.
     */
    private static function setComposerHome(string|false $composerHome): void
    {
        if (false === $composerHome) {
            putenv('COMPOSER_HOME');
            unset($_SERVER['COMPOSER_HOME'], $_ENV['COMPOSER_HOME']);

            return;
        }

        putenv('COMPOSER_HOME='.$composerHome);
        $_SERVER['COMPOSER_HOME'] = $_ENV['COMPOSER_HOME'] = $composerHome;
    }
}
