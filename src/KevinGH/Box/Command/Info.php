<?php

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

/**
 * Provides information about the Phar extension or file.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class Info extends Command
{
    /**
     * The list of recognized compression algorithms.
     *
     * @var array
     */
    private static $algorithms = array(
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
        Phar::TAR => 'TAR',
        Phar::ZIP => 'ZIP'
    );

    /**
     * The list of recognized file compression algorithms.
     *
     * @var array
     */
    private static $fileAlgorithms = array(
        Phar::BZ2 => 'BZ2',
        Phar::GZ => 'GZ',
    );

    /**
     * @override
     */
    protected function configure()
    {
        $this->setName('info');
        $this->setDescription(
            'Displays information about the Phar extension or file.'
        );
        $this->setHelp(
            <<<HELP
The <info>%command.name%</info> command will display information about the Phar extension,
or the Phar file if specified.

If the <info>phar</info> argument <comment>(the Phar file path)</comment> is provided, information
about the Phar file itself will be displayed.

If the <info>--list|-l</info> option is used, the contents of the Phar file will
be listed. By default, the list is shown as an indented tree. You may
instead choose to view a flat listing, by setting the <info>--mode|-m</info> option
to <comment>flat</comment>.
HELP
        );
        $this->addArgument(
            'phar',
            InputArgument::OPTIONAL,
            'The Phar file.'
        );
        $this->addOption(
            'list',
            'l',
            InputOption::VALUE_NONE,
            'List the contents of the Phar?'
        );
        $this->addOption(
            'metadata',
            null,
            InputOption::VALUE_NONE,
            'Display metadata?'
        );
        $this->addOption(
            'mode',
            'm',
            InputOption::VALUE_OPTIONAL,
            'The listing mode. (default: indent, options: indent, flat)',
            'indent'
        );
    }

    /**
     * @override
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (null !== ($file = $input->getArgument('phar'))) {
            $phar = new Phar($file);
            $signature = $phar->getSignature();

            $this->render(
                $output,
                array(
                    'API Version' => $phar->getVersion(),
                    'Archive Compression' => $phar->isCompressed()
                        ? self::$algorithms[$phar->isCompressed()]
                        : 'None',
                    'Signature' => $signature['hash_type'],
                    'Signature Hash' => $signature['hash']
                )
            );

            if ($input->getOption('list')) {
                $output->writeln('');
                $output->writeln('<comment>Contents:</comment>');

                $root = 'phar://' . str_replace('\\', '/', realpath($file)) . '/';

                $this->contents(
                    $output,
                    $phar,
                    ('indent' === $input->getOption('mode')) ? 0 : false,
                    $root,
                    $phar,
                    $root
                );
            }

            if ($input->getOption('metadata')) {
                $output->writeln('');
                $output->writeln('<comment>Metadata:</comment>');
                $output->writeln(var_export($phar->getMetadata(), true));
            }

            unset($phar);
        } else {
            $this->render(
                $output,
                array(
                    'API Version' => Phar::apiVersion(),
                    'Supported Compression' => Phar::getSupportedCompression(),
                    'Supported Signatures' => Phar::getSupportedSignatures()
                )
            );
        }
    }

    /**
     * Renders the contents of an iterator.
     *
     * @param OutputInterface $output The output handler.
     * @param Traversable     $list   The traversable list.
     * @param boolean|integer $indent The indentation level.
     * @param string          $base   The base path.
     * @param Phar            $phar   The PHP archive.
     * @param string          $root   The root path to remove.
     */
    private function contents(
        OutputInterface $output,
        Traversable $list,
        $indent,
        $base,
        Phar $phar,
        $root
    ) {
        /** @var PharFileInfo $item */
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
                $output->writeln("<info>$path</info>");
            } else {
                $compression = '';

                foreach (self::$fileAlgorithms as $code => $name) {
                    if ($item->isCompressed($code)) {
                        $compression = " <fg=cyan>[$name]</fg=cyan>";
                    }
                }

                $output->writeln($path . $compression);
            }

            if ($item->isDir()) {
                $this->contents(
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

    /**
     * Renders the list of attributes.
     *
     * @param OutputInterface $output     The output.
     * @param array           $attributes The list of attributes.
     */
    private function render(OutputInterface $output, array $attributes)
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
}
