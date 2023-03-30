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

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\Input\IO;
use KevinGH\Box\Pharaoh\InvalidPhar;
use KevinGH\Box\Pharaoh\Pharaoh;
use ParagonIE\ConstantTime\Hex;
use Phar;
use PharData;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;
use UnexpectedValueException;
use function file_exists;
use function KevinGH\Box\check_php_settings;
use function KevinGH\Box\FileSystem\copy;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use function realpath;
use function sprintf;
use const DIRECTORY_SEPARATOR;

/**
 * @private
 */
final class Extract implements Command
{
    private const PHAR_ARG = 'phar';
    private const OUTPUT_ARG = 'output';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'extract',
            '🚚  Extracts a given PHAR into a directory.',
            '',
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The path to PHAR file.',
                ),
                new InputArgument(
                    self::OUTPUT_ARG,
                    InputArgument::REQUIRED,
                    'The output directory.',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $pharPath = self::getPharFilePath($io);
        $outputDir = $io->getArgument(self::OUTPUT_ARG)->asNonEmptyString();

        if (null === $pharPath) {
            return ExitCode::FAILURE;
        }

        try {
            self::dumpPhar($pharPath, $outputDir);
        } catch (Throwable $throwable) {
            remove($outputDir);

            throw $throwable;
        }

        return ExitCode::SUCCESS;
    }

    private static function getPharFilePath(IO $io): ?string
    {
        $filePath = realpath($io->getArgument(self::PHAR_ARG)->asString());

        if (false !== $filePath) {
            return $filePath;
        }

        $io->error(
            sprintf(
                'The file "%s" could not be found.',
                $io->getArgument(self::PHAR_ARG)->asRaw(),
            ),
        );

        return null;
    }

    private static function dumpPhar(string $file, string $tmpDir): string
    {
        // We have to give every one a different alias, or it pukes.
        $alias = self::generateAlias($file);

        // Create a temporary PHAR: this is because the extension might be
        // missing in which case we would not be able to create a Phar instance
        // as it requires the .phar extension.
        $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.$alias;

        copy($file, $tmpFile, true);

        $phar = self::createPhar($file, $tmpFile);

        $phar->extractTo($tmpDir);

        // Cleanup the temporary PHAR.
        remove($tmpFile);

        return $tmpDir;
    }

    private static function generateAlias(string $file): string
    {
        $extension = self::getExtension($file);

        return Hex::encode(random_bytes(16)).$extension;
    }

    private static function getExtension(string $file): string
    {
        $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        $extension = '';

        while ('' !== $lastExtension) {
            $extension = '.'.$lastExtension.$extension;
            $file = mb_substr($file, 0, -(mb_strlen($lastExtension) + 1));
            $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        }

        return '' === $extension ? '.phar' : $extension;
    }

    private static function createPhar(string $file, string $tmpFile): Phar|PharData
    {
        try {
            return new Phar($tmpFile);
        } catch (UnexpectedValueException $cannotCreatePhar) {
            throw InvalidPhar::create($file, $cannotCreatePhar);
        }
    }
}
