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
use Fidry\Console\IO;
use Fidry\FileSystem\FS;
use KevinGH\Box\Console\Php\PhpSettingsChecker;
use KevinGH\Box\Phar\PharFactory;
use KevinGH\Box\Phar\PharMeta;
use KevinGH\Box\Phar\Throwable\InvalidPhar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;
use function bin2hex;
use function file_exists;
use function realpath;
use function sprintf;
use const DIRECTORY_SEPARATOR;

/**
 * @private
 */
final class ExtractCommand implements Command
{
    public const STUB_PATH = '.phar/stub.php';
    public const PHAR_META_PATH = '.phar/meta.json';

    private const PHAR_ARG = 'phar';
    private const OUTPUT_ARG = 'output';
    private const INTERNAL_OPT = 'internal';

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'extract',
            '🚚  Extracts a given PHAR into a directory',
            '',
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::REQUIRED,
                    'The path to the PHAR file',
                ),
                new InputArgument(
                    self::OUTPUT_ARG,
                    InputArgument::REQUIRED,
                    'The output directory',
                ),
            ],
            [
                new InputOption(
                    self::INTERNAL_OPT,
                    null,
                    InputOption::VALUE_NONE,
                    'Internal option; Should not be used.',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        PhpSettingsChecker::check($io);

        $pharPath = self::getPharFilePath($io);
        $outputDir = $io->getTypedArgument(self::OUTPUT_ARG)->asNonEmptyString();
        $internal = $io->getTypedOption(self::INTERNAL_OPT)->asBoolean();

        if (null === $pharPath) {
            return ExitCode::FAILURE;
        }

        if (file_exists($outputDir)) {
            $canDelete = $io->askQuestion(
                new ConfirmationQuestion(
                    'The output directory already exists. Do you want to delete its current content?',
                    // If is interactive, we want the prompt to default to false since it can be an error made by the user.
                    // Otherwise, this is likely launched by a script or Pharaoh in which case we do not care.
                    $internal,
                ),
            );

            if ($canDelete) {
                FS::remove($outputDir);
            // Continue
            } else {
                // Do nothing
                return ExitCode::FAILURE;
            }
        }

        FS::mkdir($outputDir);

        try {
            self::dumpPhar($pharPath, $outputDir);
        } catch (InvalidPhar $invalidPhar) {
            if (!$internal) {
                throw $invalidPhar;
            }

            $io->getErrorIO()->write($invalidPhar->getMessage());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private static function getPharFilePath(IO $io): ?string
    {
        $filePath = realpath($io->getTypedArgument(self::PHAR_ARG)->asString());

        if (false !== $filePath) {
            return $filePath;
        }

        $io->error(
            sprintf(
                'The file "%s" could not be found.',
                $io->getTypedArgument(self::PHAR_ARG)->asRaw(),
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
        $pubKey = $file.'.pubkey';
        $pubKeyContent = null;
        $tmpPubKey = $tmpFile.'.pubkey';
        $stub = $tmpDir.DIRECTORY_SEPARATOR.self::STUB_PATH;

        try {
            FS::copy($file, $tmpFile, true);

            if (file_exists($pubKey)) {
                FS::copy($pubKey, $tmpPubKey, true);
                $pubKeyContent = FS::getFileContents($pubKey);
            }

            $phar = PharFactory::create($tmpFile, $file);
            $pharMeta = PharMeta::fromPhar($phar, $pubKeyContent);

            $phar->extractTo($tmpDir);
            FS::dumpFile($stub, $phar->getStub());
        } catch (Throwable $throwable) {
            FS::remove([$tmpFile, $tmpPubKey]);

            throw $throwable;
        }

        FS::dumpFile(
            $tmpDir.DIRECTORY_SEPARATOR.self::PHAR_META_PATH,
            $pharMeta->toJson(),
        );

        // Cleanup the temporary PHAR.
        FS::remove([$tmpFile, $tmpPubKey]);

        return $tmpDir;
    }

    private static function generateAlias(string $file): string
    {
        $extension = self::getExtension($file);

        return bin2hex(random_bytes(16)).$extension;
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
}
