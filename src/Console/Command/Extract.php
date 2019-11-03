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

use function count;
use KevinGH\Box\Box;
use function KevinGH\Box\bump_open_file_descriptor_limit;
use KevinGH\Box\Console\IO\IO;
use function KevinGH\Box\create_temporary_phar;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\remove;
use function realpath;
use RecursiveIteratorIterator;
use RuntimeException;
use function sprintf;
use function strlen;
use function substr;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;

/**
 * @private
 */
final class Extract extends BaseCommand
{
    private const PHAR_ARG = 'phar';
    private const OUTPUT_ARG = 'output';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('extract');
        $this->setDescription(
            '🚚  Extracts a given PHAR into a directory'
        );
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::REQUIRED,
            'The PHAR file.'
        );
        $this->addArgument(
            self::OUTPUT_ARG,
            InputArgument::REQUIRED,
            'The output directory'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(IO $io): int
    {
        $input = $io->getInput();

        $file = realpath($input->getArgument(self::PHAR_ARG));

        if (false === $file) {
            $io->error(
                sprintf(
                    'The file "%s" could not be found.',
                    $input->getArgument(self::PHAR_ARG)
                )
            );

            return 1;
        }

        $tmpFile = create_temporary_phar($file);

        try {
            $box = Box::create($tmpFile);
        } catch (Throwable $throwable) {
            if ($io->isDebug()) {
                throw new ConsoleRuntimeException(
                    'The given file is not a valid PHAR',
                    0,
                    $throwable
                );
            }

            $io->error('The given file is not a valid PHAR');

            return 1;
        }

        $restoreLimit = bump_open_file_descriptor_limit(count($box), $io);

        $outputDir = $input->getArgument(self::OUTPUT_ARG);

        try {
            remove($outputDir);

            $rootLength = strlen('phar://'.$box->getPhar()->getPath()) + 1;

            foreach (new RecursiveIteratorIterator($box->getPhar()) as $file) {
                dump_file(
                    $outputDir.'/'.substr($file->getPathname(), $rootLength),
                    (string) $file->getContent()
                );
            }
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return 1;
        } finally {
            $restoreLimit();

            remove($tmpFile);
        }

        return 0;
    }
}
