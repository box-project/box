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

use function array_filter;
use function array_flip;
use DirectoryIterator;
use function is_array;
use KevinGH\Box\Console\IO\IO;
use KevinGH\Box\Console\PharInfoRenderer;
use function KevinGH\Box\create_temporary_phar;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_phar_compression_algorithms;
use KevinGH\Box\PharInfo\PharInfo;
use Phar;
use PharData;
use PharFileInfo;
use function realpath;
use function sprintf;
use function str_repeat;
use function str_replace;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Throwable;
use Webmozart\Assert\Assert;

/**
 * @private
 */
final class Info extends BaseCommand
{
    private const PHAR_ARG = 'phar';
    private const LIST_OPT = 'list';
    private const METADATA_OPT = 'metadata';
    private const MODE_OPT = 'mode';
    private const DEPTH_OPT = 'depth';

    private static array $FILE_ALGORITHMS;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        if (!isset(self::$FILE_ALGORITHMS)) {
            self::$FILE_ALGORITHMS = array_flip(array_filter(get_phar_compression_algorithms()));
        }
    }

    protected function configure(): void
    {
        $this->setName('info');
        $this->setDescription(
            '🔍  Displays information about the PHAR extension or file'
        );
        $this->setHelp(
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
        );
        $this->addArgument(
            self::PHAR_ARG,
            InputArgument::OPTIONAL,
            'The Phar file.'
        );
        $this->addOption(
            self::LIST_OPT,
            'l',
            InputOption::VALUE_NONE,
            'List the contents of the Phar?'
        );
        $this->addOption(
            self::METADATA_OPT,
            null,
            InputOption::VALUE_NONE,
            'Display metadata?'
        );
        $this->addOption(
            self::MODE_OPT,
            'm',
            InputOption::VALUE_REQUIRED,
            'The listing mode. (default: indent, options: indent, flat)',
            'indent'
        );
        $this->addOption(
            self::DEPTH_OPT,
            'd',
            InputOption::VALUE_REQUIRED,
            'The depth of the tree displayed',
            -1
        );
    }

    public function executeCommand(IO $io): int
    {
        $input = $io->getInput();

        $io->newLine();

        $file = $input->getArgument(self::PHAR_ARG);

        if (null === $file) {
            return $this->showGlobalInfo($io);
        }

        $file = Path::canonicalize($file);

        /** @var string $file */
        $fileRealPath = realpath($file);

        if (false === $fileRealPath) {
            $io->error(
                sprintf(
                    'The file "%s" could not be found.',
                    $file
                )
            );

            return 1;
        }

        $tmpFile = create_temporary_phar($fileRealPath);

        try {
            return $this->showInfo($tmpFile, $fileRealPath, $io);
        } finally {
            remove($tmpFile);
        }
    }

    public function showInfo(string $file, string $originalFile, IO $io): int
    {
        $input = $io->getInput();

        $depth = (int) $input->getOption(self::DEPTH_OPT);

        Assert::greaterThanEq($depth, -1, 'Expected the depth to be a positive integer or -1, got "%d"');

        try {
            $pharInfo = new PharInfo($file);

            return $this->showPharInfo(
                $pharInfo,
                $input->getOption(self::LIST_OPT),
                $depth,
                'indent' === $input->getOption(self::MODE_OPT),
                $io
            );
        } catch (Throwable $throwable) {
            if ($io->isDebug()) {
                throw $throwable;
            }

            $io->error(
                sprintf(
                    'Could not read the file "%s".',
                    $originalFile
                )
            );

            return 1;
        }
    }

    private function showGlobalInfo(IO $io): int
    {
        $this->render(
            $io,
            [
                'API Version' => Phar::apiVersion(),
                'Supported Compression' => Phar::getSupportedCompression(),
                'Supported Signatures' => Phar::getSupportedSignatures(),
            ]
        );

        $io->newLine();
        $io->comment('Get a PHAR details by giving its path as an argument.');

        return 0;
    }

    private function showPharInfo(
        PharInfo $pharInfo,
        bool $content,
        int $depth,
        bool $indent,
        IO $io
    ): int {
        $this->showPharMeta($pharInfo, $io);

        if ($content) {
            $this->renderContents(
                $io,
                $pharInfo->getPhar(),
                0,
                $depth,
                $indent ? 0 : false,
                $pharInfo->getRoot(),
                $pharInfo->getPhar(),
                $pharInfo->getRoot()
            );
        } else {
            $io->comment('Use the <info>--list|-l</info> option to list the content of the PHAR.');
        }

        return 0;
    }

    private function showPharMeta(PharInfo $pharInfo, IO $io): void
    {
        $io->writeln(
            sprintf(
                '<comment>API Version:</comment> %s',
                $pharInfo->getVersion()
            )
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

    private function render(IO $io, array $attributes): void
    {
        $out = false;

        foreach ($attributes as $name => $value) {
            if ($out) {
                $io->writeln('');
            }

            $io->write("<comment>$name:</comment>");

            if (is_array($value)) {
                $io->writeln('');

                foreach ($value as $v) {
                    $io->writeln("  - $v");
                }
            } else {
                $io->writeln(" $value");
            }

            $out = true;
        }
    }

    /**
     * @param iterable|PharFileInfo[] $list
     * @param false|int               $indent Nbr of indent or `false`
     */
    private function renderContents(
        OutputInterface $output,
        iterable $list,
        int $depth,
        int $maxDepth,
        int|false $indent,
        string $base,
        Phar|PharData $phar,
        string $root
    ): void {
        if (-1 !== $maxDepth && $depth > $maxDepth) {
            return;
        }

        foreach ($list as $item) {
            /** @var PharFileInfo $item */
            $item = $phar[str_replace($root, '', $item->getPathname())];

            if (false !== $indent) {
                $output->write(str_repeat(' ', $indent));

                $path = $item->getFilename();

                if ($item->isDir()) {
                    $path .= '/';
                }
            } else {
                $path = str_replace($base, '', $item->getPathname());
            }

            if ($item->isDir()) {
                if (false !== $indent) {
                    $output->writeln("<info>$path</info>");
                }
            } else {
                $compression = '<fg=red>[NONE]</fg=red>';

                foreach (self::$FILE_ALGORITHMS as $code => $name) {
                    if ($item->isCompressed($code)) {
                        $compression = "<fg=cyan>[$name]</fg=cyan>";
                        break;
                    }
                }

                $fileSize = format_size($item->getCompressedSize());

                $output->writeln(
                    sprintf(
                        '%s %s - %s',
                        $path,
                        $compression,
                        $fileSize
                    )
                );
            }

            if ($item->isDir()) {
                $this->renderContents(
                    $output,
                    new DirectoryIterator($item->getPathname()),
                    $depth + 1,
                    $maxDepth,
                    false === $indent ? $indent : $indent + 2,
                    $base,
                    $phar,
                    $root
                );
            }
        }
    }
}
