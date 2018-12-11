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
use DirectoryIterator;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use UnexpectedValueException;
use function array_fill_keys;
use function array_filter;
use function array_reduce;
use function array_sum;
use function count;
use function end;
use function filesize;
use function is_array;
use function iterator_to_array;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\format_size;
use function key;
use function realpath;
use function sprintf;
use function str_repeat;
use function str_replace;
use function var_export;

/**
 * @private
 */
final class Info extends Command
{
    use CreateTemporaryPharFile;

    private const PHAR_ARG = 'phar';
    private const LIST_OPT = 'list';
    private const METADATA_OPT = 'metadata';
    private const MODE_OPT = 'mode';
    private const DEPTH_OPT = 'depth';

    /**
     * The list of recognized compression algorithms.
     */
    private const ALGORITHMS = [
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
        Phar::NONE => 'None',
    ];

    /**
     * The list of recognized file compression algorithms.
     */
    private const FILE_ALGORITHMS = [
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
    ];

    /**
     * {@inheritdoc}
     */
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
HELP
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

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        if (null === ($file = $input->getArgument(self::PHAR_ARG))) {
            return $this->showGlobalInfo($output, $io);
        }

        $file = realpath($file);

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

        try {
            return $this->showInfo($tmpFile, $file, $input, $output, $io);
        } finally {
            if ($file !== $tmpFile) {
                remove($tmpFile);
            }
        }
    }

    public function showInfo(string $file, string $originalFile, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $depth = (int) $input->getOption(self::DEPTH_OPT);

        Assertion::greaterOrEqualThan($depth, -1, 'Expected the depth to be a positive integer or -1, got "%d"');

        try {
            try {
                $phar = new Phar($file);
            } catch (UnexpectedValueException $exception) {
                $phar = new PharData($file);
            }

            return $this->showPharInfo(
                $phar,
                $input->getOption(self::LIST_OPT),
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

    /**
     * @param Phar|PharData $phar
     */
    private function showPharInfo(
        $phar,
        bool $content,
        int $depth,
        bool $indent,
        OutputInterface $output,
        SymfonyStyle $io
    ): int {
        $signature = $phar->getSignature();

        $this->showPharGlobalInfo($phar, $io, $signature);

        if ($content) {
            $root = 'phar://'.str_replace('\\', '/', realpath($phar->getPath())).'/';

            $this->renderContents(
                $output,
                $phar,
                0,
                $depth,
                $indent ? 0 : false,
                $root,
                $phar,
                $root
            );
        } else {
            $io->comment('Use the <info>--list|-l</info> option to list the content of the PHAR.');
        }

        return 0;
    }

    /**
     * @param Phar|PharData $phar
     * @param mixed         $signature
     */
    private function showPharGlobalInfo($phar, SymfonyStyle $io, $signature): void
    {
        $io->writeln(
            sprintf(
                '<comment>API Version:</comment> %s',
                '' !== $phar->getVersion() ? $phar->getVersion() : 'No information found'
            )
        );
        $io->newLine();

        $count = array_filter($this->retrieveCompressionCount($phar));
        $totalCount = array_sum($count);

        if (1 === count($count)) {
            $io->writeln(
                sprintf(
                    '<comment>Archive Compression:</comment> %s',
                    key($count)
                )
            );
        } else {
            $io->writeln('<comment>Archive Compression:</comment>');

            end($count);
            $lastAlgorithmName = key($count);

            $totalPercentage = 100;

            foreach ($count as $algorithmName => $nbrOfFiles) {
                if ($lastAlgorithmName === $algorithmName) {
                    $percentage = $totalPercentage;
                } else {
                    $percentage = $nbrOfFiles * 100 / $totalCount;

                    $totalPercentage -= $percentage;
                }

                $io->writeln(
                    sprintf(
                        '  - %s (%0.2f%%)',
                        $algorithmName,
                        $percentage
                    )
                );
            }
        }
        $io->newLine();

        if (false !== $signature) {
            $io->writeln(
                sprintf(
                    '<comment>Signature:</comment> %s',
                    $signature['hash_type']
                )
            );
            $io->writeln(
                sprintf(
                    '<comment>Signature Hash:</comment> %s',
                    $signature['hash']
                )
            );
            $io->newLine();
        }

        $metadata = var_export($phar->getMetadata(), true);

        if ('NULL' === $metadata) {
            $io->writeln('<comment>Metadata:</comment> None');
        } else {
            $io->writeln('<comment>Metadata:</comment>');
            $io->writeln($metadata);
        }
        $io->newLine();

        $io->writeln(
            sprintf(
                '<comment>Contents:</comment>%s (%s)',
                1 === $totalCount ? ' 1 file' : " $totalCount files",
                format_size(
                    filesize($phar->getPath())
                )
            )
        );
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

                foreach (self::FILE_ALGORITHMS as $code => $name) {
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

    /**
     * @param Phar|PharData $phar
     */
    private function retrieveCompressionCount($phar): array
    {
        $count = array_fill_keys(
            self::ALGORITHMS,
            0
        );

        if ($phar instanceof PharData) {
            $count[self::ALGORITHMS[$phar->isCompressed()]] = 1;

            return $count;
        }

        $countFile = static function (array $count, PharFileInfo $file): array {
            if (false === $file->isCompressed()) {
                ++$count['None'];

                return $count;
            }

            foreach (self::ALGORITHMS as $compressionAlgorithmCode => $compressionAlgorithmName) {
                if ($file->isCompressed($compressionAlgorithmCode)) {
                    ++$count[$compressionAlgorithmName];

                    return $count;
                }
            }

            return $count;
        };

        return array_reduce(
            iterator_to_array(new RecursiveIteratorIterator($phar), true),
            $countFile,
            $count
        );
    }
}
