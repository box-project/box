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

namespace KevinGH\Box\Console\Command\Info;

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration as ConsoleConfiguration;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\Command\ChangeWorkingDirOption;
use KevinGH\Box\Console\Command\ConfigOption;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\Requirement;
use KevinGH\Box\RequirementChecker\Requirements as RequirementsCollection;
use KevinGH\Box\RequirementChecker\RequirementType;
use stdClass;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use function array_map;
use function count;
use function is_array;
use function iter\filter;
use function iter\toArray;
use function sprintf;

final readonly class RequirementsCommand implements Command
{
    private const NO_CONFIG_OPTION = 'no-config';

    public function __construct(
        private AppRequirementsFactory $factory,
    ) {
    }

    public function getConfiguration(): ConsoleConfiguration
    {
        return new ConsoleConfiguration(
            'info:requirements',
            'Lists the application requirements found.',
            'The <info>%command.name%</info> command will list the PHP versions and extensions required to run the built PHAR.',
            options: [
                new InputOption(
                    self::NO_CONFIG_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Ignore the config file even when one is specified with the `--config` option.',
                ),
                ConfigOption::getOptionInput(),
                ChangeWorkingDirOption::getOptionInput(),
            ],
        );
    }

    public function execute(IO $io): int
    {
        ChangeWorkingDirOption::changeWorkingDirectory($io);

        $config = $io->getTypedOption(self::NO_CONFIG_OPTION)->asBoolean()
            ? Configuration::create(null, new stdClass())
            : ConfigOption::getConfig($io, true);

        $composerJson = $config->getComposerJson();
        $composerLock = $config->getComposerLock();

        if (null === $composerJson) {
            $io->error('Could not find a composer.json file.');

            return ExitCode::FAILURE;
        }

        if (null === $composerLock) {
            $io->error('Could not find a composer.lock file.');

            return ExitCode::FAILURE;
        }

        [
            $phpRequirements,
            $requiredExtensions,
            $conflictingExtensions,
        ] = $this->getAllRequirements(
            $composerJson,
            $composerLock,
            $config,
        );

        $optimizedExtensionRequirements = $this->getOptimizedExtensionRequirements(
            $composerJson,
            $composerLock,
            $config,
        );

        self::renderRequiredPHPVersionsSection($phpRequirements, $io);
        $io->newLine();

        self::renderExtensionsSection(
            $requiredExtensions,
            $io,
        );
        $io->newLine();

        self::renderOptimizedRequiredExtensionsSection($optimizedExtensionRequirements, $io);
        $io->newLine();

        self::renderConflictingExtensionsSection($conflictingExtensions, $io);

        return ExitCode::SUCCESS;
    }

    /**
     * @return array{Requirement[], Requirement[], Requirement[]}
     */
    private function getAllRequirements(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        Configuration $config,
    ): array
    {
        $requirements = $this->factory->createUnfiltered(
            $composerJson,
            $composerLock,
            $config->getCompressionAlgorithm(),
        );

        return self::filterRequirements($requirements);
    }

    /**
     * @return Requirement[]
     */
    private function getOptimizedExtensionRequirements(
        ComposerJson $composerJson,
        ComposerLock $composerLock,
        Configuration $config,
    ): array
    {
        $optimizedRequirements = $this->factory->create(
            $composerJson,
            $composerLock,
            $config->getCompressionAlgorithm(),
        );

        $isExtension = static fn (Requirement $requirement) => RequirementType::EXTENSION === $requirement->type;

        return toArray(
            filter($isExtension, $optimizedRequirements),
        );
    }

    /**
     * @return array{Requirement[], Requirement[], Requirement[], Requirement[]}
     */
    private static function filterRequirements(RequirementsCollection $requirements): array
    {
        $phpRequirements = [];
        $requiredExtensions = [];
        $conflictingExtensions = [];

        foreach ($requirements as $requirement) {
            /** @var Requirement $requirement */
            switch ($requirement->type) {
                case RequirementType::PHP:
                    $phpRequirements[] = $requirement;
                    break;

                case RequirementType::EXTENSION:
                case RequirementType::PROVIDED_EXTENSION:
                    $requiredExtensions[] = $requirement;
                    break;

                case RequirementType::EXTENSION_CONFLICT:
                    $conflictingExtensions[] = $requirement;
                    break;
            }
        }

        return [
            $phpRequirements,
            $requiredExtensions,
            $conflictingExtensions,
        ];
    }

    /**
     * @param Requirement[] $requirements
     */
    private static function renderRequiredPHPVersionsSection(
        array $requirements,
        IO    $io,
    ): void {
        if (0 === count($requirements)) {
            $io->writeln('<comment>No PHP constraint found.</comment>');

            return;
        }

        $io->writeln('<comment>The following PHP constraints were found:</comment>');

        self::renderTable(
            $io,
            ['Constraints', 'Source'],
            array_map(
                static fn (Requirement $requirement) => [
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ],
                $requirements,
            ),
        );
    }

    /**
     * @param Requirement[] $required
     */
    private static function renderExtensionsSection(
        array $required,
        IO $io,
    ): void {
        if (0 === count($required)) {
            $io->writeln('<comment>No extension constraint found.</comment>');

            return;
        }

        $io->writeln('<comment>The following extensions constraints were found:</comment>');

        self::renderTable(
            $io,
            ['Type', 'Extension', 'Source'],
            array_map(
                static fn (Requirement $requirement) => [
                    match ($requirement->type) {
                        RequirementType::EXTENSION => 'required',
                        RequirementType::PROVIDED_EXTENSION => 'provided',
                    },
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ],
                $required,
            ),
        );
    }

    /**
     * @param Requirement[] $required
     */
    private static function renderOptimizedRequiredExtensionsSection(
        array $required,
        IO $io,
    ): void {
        $io->writeln('The required and provided extensions constraints (see above) are resolved to compute the final required extensions.');

        if (0 === count($required)) {
            $io->writeln('<comment>The application does not have any extension constraint.</comment>');

            return;
        }

        $io->writeln('<comment>The application requires the following extension constraints:</comment>');

        self::renderTable(
            $io,
            ['Extension', 'Source'],
            array_map(
                static fn (Requirement $requirement) => [
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ],
                $required,
            ),
        );
    }

    /**
     * @param Requirement[] $conflicting
     */
    private static function renderConflictingExtensionsSection(
        array $conflicting,
        IO $io,
    ): void {
        if (0 === count($conflicting)) {
            $io->writeln('<comment>No conflicting extension found.</comment>');

            return;
        }

        $io->writeln('<comment>Conflicting extensions:</comment>');

        self::renderTable(
            $io,
            ['Extension', 'Source'],
            array_map(
                static fn (Requirement $requirement) => [
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ],
                $conflicting,
            ),
        );
    }

    private static function renderTable(
        IO $io,
        array $headers,
        array|TableSeparator ...$rowsList,
    ): void
    {
        /** @var Table $table */
        $table = $io->createTable();
        $table->setStyle('box');

        $table->setHeaders($headers);

        foreach ($rowsList as $rowsOrTableSeparator) {
            if (is_array($rowsOrTableSeparator)) {
                $table->addRows($rowsOrTableSeparator);
            } else {
                $table->addRow($rowsOrTableSeparator);
            }
        }

        $table->render();
    }
}
