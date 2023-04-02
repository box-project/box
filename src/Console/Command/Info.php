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

use DirectoryIterator;
use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\Input\IO;
use KevinGH\Box\Console\PharInfoRenderer;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Pharaoh\Pharaoh;
use Phar;
use PharData;
use PharFileInfo;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Throwable;
use function implode;
use function is_array;
use function KevinGH\Box\format_size;
use function realpath;
use function sprintf;
use function str_repeat;
use function str_replace;

/**
 * @private
 */
final class Info implements Command
{
    private const PHAR_ARG = 'phar';
    private const LIST_OPT = 'list';
    private const MODE_OPT = 'mode';
    private const DEPTH_OPT = 'depth';

    private const MODES = [
        'indent',
        'flat',
    ];

    public function getConfiguration(): Configuration
    {
        return new Configuration(
            'info',
            '🔍  Displays information about the PHAR extension or file',
            <<<'HELP'
                The <info>%command.name%</info> command will display information about the Phar extension,
                or the Phar file if specified.

                If the <info>phar</info> argument <comment>(the PHAR file path)</comment> is provided, information
                about the PHAR file itself will be displayed.

                If the <info>--list|-l</info> option is used, the contents of the PHAR file will
                be listed. By default, the list is shown as an indented tree. You may
                instead choose to view a flat listing, by setting the <info>--mode|-m</info> option
                to <comment>flat</comment>.
                HELP,
            [
                new InputArgument(
                    self::PHAR_ARG,
                    InputArgument::OPTIONAL,
                    'The Phar file.',
                ),
            ],
            [
                new InputOption(
                    self::LIST_OPT,
                    'l',
                    InputOption::VALUE_NONE,
                    'List the contents of the Phar?',
                ),
                new InputOption(
                    self::MODE_OPT,
                    'm',
                    InputOption::VALUE_REQUIRED,
                    sprintf(
                        'The listing mode. Modes available: "%s"',
                        implode('", "', self::MODES),
                    ),
                    'indent',
                ),
                new InputOption(
                    self::DEPTH_OPT,
                    'd',
                    InputOption::VALUE_REQUIRED,
                    'The depth of the tree displayed',
                    '-1',
                ),
            ],
        );
    }

    public function execute(IO $io): int
    {
        $io->newLine();

        $file = $io->getArgument(self::PHAR_ARG)->asNullableNonEmptyString();

        if (null === $file) {
            return self::showGlobalInfo($io);
        }

        $file = Path::canonicalize($file);
        $fileRealPath = realpath($file);

        if (false === $fileRealPath) {
            $io->error(
                sprintf(
                    'The file "%s" could not be found.',
                    $file,
                ),
            );

            return ExitCode::FAILURE;
        }

        return self::showInfo($fileRealPath, $io);
    }

    public static function showInfo(string $file, IO $io): int
    {
        $maxDepth = self::getMaxDepth($io);
        $mode = $io->getOption(self::MODE_OPT)->asStringChoice(self::MODES);

        try {
            $pharInfo = new Pharaoh($file);

            return self::showPharInfo(
                $pharInfo,
                $io->getOption(self::LIST_OPT)->asBoolean(),
                $maxDepth,
                'indent' === $mode,
                $io,
            );
        } catch (Throwable $throwable) {
            if ($io->isDebug()) {
                throw $throwable;
            }

            $io->error(
                sprintf(
                    'Could not read the file "%s".',
                    $file,
                ),
            );

            return ExitCode::FAILURE;
        }
    }

    /**
     * @return -1|natural
     */
    private static function getMaxDepth(IO $io): int
    {
        $option = $io->getOption(self::DEPTH_OPT);

        return '-1' === $option->asRaw()
            ? -1
            : $option->asNatural(sprintf(
                'Expected the depth to be a positive integer or -1: "%s".',
                $option->asRaw(),
            ));
    }

    private static function showGlobalInfo(IO $io): int
    {
        self::render(
            $io,
            [
                'API Version' => Phar::apiVersion(),
                'Supported Compression' => Phar::getSupportedCompression(),
                'Supported Signatures' => Phar::getSupportedSignatures(),
            ],
        );

        $io->newLine();
        $io->comment('Get a PHAR details by giving its path as an argument.');

        return ExitCode::SUCCESS;
    }

    private static function showPharInfo(
        Pharaoh $pharInfo,
        bool $content,
        int $depth,
        bool $indent,
        IO $io,
    ): int {
        self::showPharMeta($pharInfo, $io);

        if ($content) {
            self::renderContents(
                $io,
                $pharInfo->getFiles(),
                0,
                $depth,
                $indent ? 0 : false,
                $pharInfo->getRoot(),
                $pharInfo,
                $pharInfo->getRoot(),
            );
        } else {
            $io->comment('Use the <info>--list|-l</info> option to list the content of the PHAR.');
        }

        return ExitCode::SUCCESS;
    }

    private static function showPharMeta(Pharaoh $pharInfo, IO $io): void
    {
        $io->writeln(
            sprintf(
                '<comment>API Version:</comment> %s',
                $pharInfo->getVersion(),
            ),
        );

        $io->newLine();

        PharInfoRenderer::renderCompression($pharInfo, $io);

        $io->newLine();

        PharInfoRenderer::renderSignature($pharInfo, $io);

        $io->newLine();

        PharInfoRenderer::renderMetadata($pharInfo, $io);

        $io->newLine();

        PharInfoRenderer::renderContentsSummary($pharInfo, $io);
    }

    private static function render(IO $io, array $attributes): void
    {
        $out = false;

        foreach ($attributes as $name => $value) {
            if ($out) {
                $io->writeln('');
            }

            $io->write("<comment>{$name}:</comment>");

            if (is_array($value)) {
                $io->writeln('');

                foreach ($value as $v) {
                    $io->writeln("  - {$v}");
                }
            } else {
                $io->writeln(" {$value}");
            }

            $out = true;
        }
    }

    /**
     * @param iterable<PharFileInfo> $source
     * @param -1|natural             $maxDepth
     * @param false|int              $indent   Nbr of indent or `false`
     */
    private static function renderContents(
        OutputInterface $output,
        iterable        $source,
        int             $depth,
        int             $maxDepth,
        int|false       $indent,
        string          $base,
        Pharaoh   $pharInfo,
    ): void {
        if (-1 !== $maxDepth && $depth > $maxDepth) {
            return;
        }

        foreach ($source as $relativePath => $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (false !== $indent) {
                $output->write(str_repeat(' ', $indent));

                $path = $fileInfo->getFilename();

                if ($fileInfo->isDir()) {
                    $path .= '/';
                }
            } else {
                $path = str_replace($base, '', $fileInfo->getPathname());
            }

            if ($fileInfo->isDir()) {
                if (false !== $indent) {
                    $output->writeln("<info>{$path}</info>");
                }
            } else {
                [
                    'compression' => $compression,
                    'compressedSize' => $compressionSize,
                ] = $pharInfo->getFileMeta($relativePath);

                $compressionLine = CompressionAlgorithm::NONE === $compression
                    ? '<fg=red>[NONE]</fg=red>'
                    : "<fg=cyan>[{$compression->name}]</fg=cyan>";

                $output->writeln(
                    sprintf(
                        '%s %s - %s',
                        $path,
                        $compressionLine,
                        format_size($compressionSize),
                    ),
                );
            }

            if ($fileInfo->isDir()) {
                self::renderContents(
                    $output,
                    new DirectoryIterator($fileInfo->getPathname()),
                    $depth + 1,
                    $maxDepth,
                    false === $indent ? $indent : $indent + 2,
                    $base,
                    $pharInfo,
                );
            }
        }
    }
}
