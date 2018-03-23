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
use Phar;
use PharFileInfo;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_fill_keys;
use function array_filter;
use function array_reduce;
use function array_sum;
use function end;
use function is_array;
use function iterator_to_array;
use function KevinGH\Box\formatted_filesize;
use function key;
use function realpath;
use function sprintf;

final class Info extends Command
{
    private const PHAR_ARG = 'phar';
    private const LIST_OPT = 'list';
    private const METADATA_OPT = 'metadata';
    private const MODE_OPT = 'mode';

    /**
     * The list of recognized compression algorithms.
     *
     * @var array
     */
    private const ALGORITHMS = [
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
        'NONE' => 'None',
    ];

    /**
     * The list of recognized file compression algorithms.
     *
     * @var array
     */
    private const FILE_ALGORITHMS = [
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
    ];

    /**
     * @override
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('');

        if (null === ($file = $input->getArgument(self::PHAR_ARG))) {
            return $this->executeShowGlobalInfo($output, $io);
        }

        $phar = new Phar($file);

        return $this->executeShowPharInfo(
            $phar,
            $input->getOption(self::LIST_OPT),
            'indent' === $input->getOption(self::MODE_OPT),
            $output,
            $io
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('info');
        $this->setDescription(
            'Displays information about the PHAR extension or file'
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
            InputOption::VALUE_OPTIONAL,
            'The listing mode. (default: indent, options: indent, flat)',
            'indent'
        );
    }

    private function executeShowGlobalInfo(OutputInterface $output, SymfonyStyle $io): int
    {
        $this->render(
            $output,
            [
                'API Version' => Phar::apiVersion(),
                'Supported Compression' => Phar::getSupportedCompression(),
                'Supported Signatures' => Phar::getSupportedSignatures(),
            ]
        );

        $io->writeln('');
        $io->comment('Run the command with the PHAR path as an argument to get details on the PHAR.');

        return 0;
    }

    private function executeShowPharInfo(Phar $phar, bool $content, bool $indent, OutputInterface $output, SymfonyStyle $io): int
    {
        $signature = $phar->getSignature();

        $this->showPharGlobalInfo($phar, $io, $signature);

        if ($content) {
            $root = 'phar://'.str_replace('\\', '/', realpath($phar->getPath())).'/';

            $this->renderContents(
                $output,
                $phar,
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

    private function showPharGlobalInfo(Phar $phar, SymfonyStyle $io, $signature): void
    {
        $io->writeln(
            sprintf(
                '<comment>API Version:</comment> %s',
                $phar->getVersion()
            )
        );
        $io->writeln('');

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
        $io->writeln('');

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
        $io->writeln('');

        $metadata = var_export($phar->getMetadata(), true);

        if ('NULL' === $metadata) {
            $io->writeln('<comment>Metadata:</comment> None');
        } else {
            $io->writeln('<comment>Metadata:</comment>');
            $io->writeln($metadata);
        }
        $io->writeln('');

        $io->writeln(
            sprintf(
                '<comment>Contents:</comment>%s (%s)',
                1 === $totalCount ? ' 1 file' : " $totalCount files",
                formatted_filesize($phar->getPath())
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
     * @param OutputInterface         $output
     * @param iterable|PharFileInfo[] $list
     * @param bool|int                $indent Nbr of indent or `false`
     * @param string                  $base
     * @param Phar                    $phar
     * @param string                  $root
     */
    private function renderContents(
        OutputInterface $output,
        iterable $list,
        $indent,
        string $base,
        Phar $phar,
        string $root
    ): void {
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
                $compression = ' <fg=red>[NONE]</fg=red>';

                foreach (self::FILE_ALGORITHMS as $code => $name) {
                    if ($item->isCompressed($code)) {
                        $compression = " <fg=cyan>[$name]</fg=cyan>";
                        break;
                    }
                }

                $output->writeln($path.$compression);
            }

            if ($item->isDir()) {
                $this->renderContents(
                    $output,
                    new DirectoryIterator($item->getPathname()),
                    (false === $indent) ? $indent : $indent + 2,
                    $base,
                    $phar,
                    $root
                );
            }
        }
    }

    private function retrieveCompressionCount(Phar $phar): array
    {
        $count = array_fill_keys(
           self::ALGORITHMS,
            0
        );

        $countFile = function (array $count, PharFileInfo $file) {
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
            iterator_to_array(new RecursiveIteratorIterator($phar)),
            $countFile,
            $count
        );
    }
}
