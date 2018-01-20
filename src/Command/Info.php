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

namespace KevinGH\Box\Command;

use DirectoryIterator;
use Phar;
use PharFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Traversable;

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
        Phar::TAR => 'TAR',
        Phar::ZIP => 'ZIP',
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
        if (null === ($file = $input->getArgument(self::PHAR_ARG))) {
            $this->render(
                $output,
                [
                    'API Version' => Phar::apiVersion(),
                    'Supported Compression' => Phar::getSupportedCompression(),
                    'Supported Signatures' => Phar::getSupportedSignatures(),
                ]
            );

            return 0;
        }

        $phar = new Phar($file);
        $signature = $phar->getSignature();

        $this->render(
            $output,
            [
                'API Version' => $phar->getVersion(),
                'Archive Compression' => $phar->isCompressed()
                    ? self::ALGORITHMS[$phar->isCompressed()]
                    : 'None',
                'Signature' => $signature['hash_type'],
                'Signature Hash' => $signature['hash'],
            ]
        );

        if ($input->getOption(self::LIST_OPT)) {
            $output->writeln('');
            $output->writeln('<comment>Contents:</comment>');

            $root = 'phar://'.str_replace('\\', '/', realpath($file)).'/';

            $this->renderContents(
                $output,
                $phar,
                ('indent' === $input->getOption(self::MODE_OPT)) ? 0 : false,
                $root,
                $phar,
                $root
            );
        }

        if ($input->getOption(self::METADATA_OPT)) {
            $output->writeln('');
            $output->writeln('<comment>Metadata:</comment>');
            $output->writeln(var_export($phar->getMetadata(), true));
        }

        return 0;
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

    /**
     * Renders the list of attributes.
     *
     * @param OutputInterface $output     The output
     * @param array           $attributes The list of attributes
     */
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
     * Renders the contents of an iterator.
     *
     * @param OutputInterface $output The output handler
     * @param Traversable     $list   The traversable list
     * @param bool|int        $indent The indentation level
     * @param string          $base   The base path
     * @param Phar            $phar   The PHP archive
     * @param string          $root   The root path to remove
     */
    private function renderContents(
        OutputInterface $output,
        Traversable $list,
        $indent,
        string $base,
        Phar $phar,
        string $root
    ): void {
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
                $output->writeln("<info>$path</info>");
            } else {
                $compression = '';

                foreach (self::FILE_ALGORITHMS as $code => $name) {
                    if ($item->isCompressed($code)) {
                        $compression = " <fg=cyan>[$name]</fg=cyan>";
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
}
