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
use Assert\Assertion;
use DirectoryIterator;
use function file_get_contents;
use function getcwd;
use function is_array;
use KevinGH\Box\Box;
use KevinGH\Box\Console\FileDescriptorManipulator;
use KevinGH\Box\Console\PharInfoRenderer;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\format_size;
use function KevinGH\Box\get_phar_compression_algorithms;
use KevinGH\Box\PharInfo\PharInfo;
use Phar;
use PharData;
use PharFileInfo;
use function realpath;
use RuntimeException;
use function sprintf;
use function str_repeat;
use function str_replace;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function var_dump;

/**
 * @private
 */
final class Extract extends Command
{
    use CreateTemporaryPharFile;

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
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->newLine();

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

        $tmpFile = $this->createTemporaryPhar($file);

        $box = Box::create($tmpFile);

        $restoreLimit = FileDescriptorManipulator::bumpOpenFileDescriptorLimit(count($box), $io);

        try {
            $box->compress(Phar::NONE);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return 1;
        } finally {
            $restoreLimit();

            remove($tmpFile);
        }

        $outputDir = $input->getArgument(self::OUTPUT_ARG);

        $box->getPhar()->decompressFiles();

        unset($box);

        Box::create($tmpFile)->getPhar()->extractTo($outputDir, null, true);

        return 0;
    }

    public function showInfo(string $file, string $originalFile, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $depth = (int) $input->getOption(self::DEPTH_OPT);

        Assertion::greaterOrEqualThan($depth, -1, 'Expected the depth to be a positive integer or -1, got "%d"');

        try {
            $pharInfo = new PharInfo($file);

            return $this->showPharInfo(
                $pharInfo,
                $input->getOption(self::OUTPUT_ARG),
                $depth,
                'indent' === $input->getOption(self::MODE_OPT),
                $output,
                $io
            );
        } catch (Throwable $throwable) {
            if ($output->isDebug()) {
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

    private function showGlobalInfo(OutputInterface $output, SymfonyStyle $io): int
    {
        $this->render(
            $output,
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
        OutputInterface $output,
        SymfonyStyle $io
    ): int {
        $this->showPharMeta($pharInfo, $io);

        if ($content) {
            $this->renderContents(
                $output,
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

    private function showPharMeta(PharInfo $pharInfo, SymfonyStyle $io): void
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

    private function render(OutputInterface $output, array $attributes): void
    {
        $out = false;

        foreach ($attributes as $name => $value) {
            if ($out) {
                $output->writeln('');
            }

            $output->write("<comment>$name:</comment>");

            if (is_array($value)) {
                $output->writeln('');

                foreach ($value as $v) {
                    $output->writeln("  - $v");
                }
            } else {
                $output->writeln(" $value");
            }

            $out = true;
        }
    }

    /**
     * @param iterable|PharFileInfo[] $list
     * @param false|int               $indent Nbr of indent or `false`
     * @param Phar|PharData           $phar
     */
    private function renderContents(
        OutputInterface $output,
        iterable $list,
        int $depth,
        int $maxDepth,
        $indent,
        string $base,
        $phar,
        string $root
    ): void {
        if (-1 !== $maxDepth && $depth > $maxDepth) {
            return;
        }

        foreach ($list as $item) {
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
