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

namespace KevinGH\Box\Console\Command;

use function chdir;
use Fidry\Console\Input\IO;
use function getcwd;
use KevinGH\Box\Console\IO\IO;
use function sprintf;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;

/**
 * @private
 */
<<<<<<<< HEAD:src/Console/Command/ChangeWorkingOption.php
final class ChangeWorkingOption
========
final class ChangeWorkingDirOption
>>>>>>>> upstream/master:src/Console/Command/ChangeWorkingDirOption.php
{
    /** @internal using a static property as traits cannot have constants */
    private const WORKING_DIR_OPT = 'working-dir';

    public static function getOptionInput(): InputOption
    {
        return new InputOption(
            self::WORKING_DIR_OPT,
            'd',
            InputOption::VALUE_REQUIRED,
            'If specified, use the given directory as working directory.',
            null,
        );
    }

    public static function changeWorkingDirectory(IO $io): void
    {
<<<<<<<< HEAD:src/Console/Command/ChangeWorkingOption.php
        $workingDir = $io->getOption(self::WORKING_DIR_OPT)->asNullableNonEmptyString();
========
        $workingDir = $io->getInput()->getOption(self::WORKING_DIR_OPT);
>>>>>>>> upstream/master:src/Console/Command/ChangeWorkingDirOption.php

        if (null === $workingDir) {
            return;
        }

        Assert::directory(
            $workingDir,
            'Could not change the working directory to "%s": directory does not exists or file is not a directory.',
        );

        if (false === chdir($workingDir)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to change the working directory to "%s" from "%s".',
                    $workingDir,
                    getcwd(),
                ),
            );
        }
    }
}
