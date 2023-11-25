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

namespace BenchTest\Console\Command;

use BenchTest\Console\PharInfoRenderer;
use BenchTest\Phar\PharInfo;
use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use Phar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Path;
use function implode;
use function is_array;
use function realpath;
use function sprintf;

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

        $file = $io->getTypedArgument(self::PHAR_ARG)->asNullableNonEmptyString();

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
        $mode = $io->getTypedOption(self::MODE_OPT)->asStringChoice(self::MODES);

        $pharInfo = new PharInfo($file);

        return self::showPharInfo(
            $pharInfo,
            $io->getTypedOption(self::LIST_OPT)->asBoolean(),
            -1 === $maxDepth ? false : $maxDepth,
            'indent' === $mode,
            $io,
        );
    }

    /**
     * @return -1|natural
     */
    private static function getMaxDepth(IO $io): int
    {
        $option = $io->getTypedOption(self::DEPTH_OPT);

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
        PharInfo $pharInfo,
        bool $content,
        int|false $maxDepth,
        bool $indent,
        IO $io,
    ): int {
        PharInfoRenderer::renderVersion($pharInfo, $io);

        $io->newLine();

        PharInfoRenderer::renderBoxVersion($pharInfo, $io);

        PharInfoRenderer::renderShortSummary(
            $pharInfo,
            $io,
            static fn () => $io->newLine(),
        );

        if ($content) {
            PharInfoRenderer::renderContent(
                $io,
                $pharInfo,
                $maxDepth,
                $indent,
            );
        } else {
            $io->newLine();
            $io->comment('Use the <info>--list|-l</info> option to list the content of the PHAR.');
        }

        return ExitCode::SUCCESS;
    }

    private static function showPharMeta(PharInfo $pharInfo, IO $io): void
    {
        PharInfoRenderer::renderVersion($pharInfo, $io);

        $io->newLine();

        PharInfoRenderer::renderShortSummary(
            $pharInfo,
            $io,
            static fn () => $io->newLine(),
        );
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
}
