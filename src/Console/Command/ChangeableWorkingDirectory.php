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

use Assert\Assertion;
use function chdir;
use function getcwd;
use function sprintf;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @private
 */
trait ChangeableWorkingDirectory
{
    /** @internal using a static property as traits cannot have constants */
    private static $WORKING_DIR_OPT = 'working-dir';

    final public function changeWorkingDirectory(InputInterface $input): void
    {
        /** @var null|string $workingDir */
        $workingDir = $input->getOption(self::$WORKING_DIR_OPT);

        if (null === $workingDir) {
            return;
        }

        Assertion::directory(
            $workingDir,
            'Could not change the working directory to "%s": directory does not exists or file is not a directory.'
        );

        if (false === chdir($workingDir)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to change the working directory to "%s" from "%s".',
                    $workingDir,
                    getcwd()
                )
            );
        }
    }

    private function configureWorkingDirOption(): void
    {
        $this->addOption(
            self::$WORKING_DIR_OPT,
            'd',
            InputOption::VALUE_REQUIRED,
            'If specified, use the given directory as working directory.',
            null
        );
    }
}
