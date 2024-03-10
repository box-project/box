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
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\Command\ChangeWorkingDirOption;
use KevinGH\Box\Console\Command\ConfigOption;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\Requirement;
use KevinGH\Box\RequirementChecker\Requirements as RequirementsCollection;
use KevinGH\Box\RequirementChecker\RequirementType;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use function array_filter;
use function array_map;
use function implode;
use function iter\filter;
use function iter\toArray;
use function sprintf;

final readonly class Requirements implements Command
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

        $requirements = $this->factory->createUnfiltered(
            $composerJson,
            $composerLock,
            $config->getCompressionAlgorithm(),
        );

        [
            $phpRequirements,
            $requiredExtensions,
            $providedExtensions,
            $conflictingExtensions,
        ] = self::filterRequirements($requirements);

        $optimizedRequiredRequirements = toArray(
            filter(
                static fn (Requirement $requirement) => $requirement->type === RequirementType::EXTENSION,
                $this->factory
                    ->create(
                        $composerJson,
                        $composerLock,
                        $config->getCompressionAlgorithm(),
                    ),
            ),
        );

        self::renderRequiredPHPVersionsSection($phpRequirements, $io);
        self::renderRequiredExtensionsSection($requiredExtensions, $io);
        self::renderProvidedExtensionsSection($providedExtensions, $io);
        self::renderOptimizedRequiredExtensionsSection($optimizedRequiredRequirements, $io);
        self::renderConflictingExtensionsSection($conflictingExtensions, $io);

        return ExitCode::SUCCESS;
    }

    private static function filterRequirements(RequirementsCollection $requirements): array
    {
        $phpRequirements = [];
        $requiredExtensions = [];
        $providedExtensions = [];
        $conflictingExtensions = [];

        foreach ($requirements as $requirement) {
            /** @var Requirement $requirement */
            switch ($requirement->type) {
                case RequirementType::PHP:
                    $phpRequirements[] = $requirement;
                    break;

                case RequirementType::EXTENSION:
                    $requiredExtensions[] = $requirement;
                    break;

                case RequirementType::PROVIDED_EXTENSION:
                    $providedExtensions[] = $requirement;
                    break;

                case RequirementType::EXTENSION_CONFLICT:
                    $conflictingExtensions[] = $requirement;
                    break;
            }
        }

        return [
            $phpRequirements,
            $requiredExtensions,
            $providedExtensions,
            $conflictingExtensions,
        ];
    }

    /**
     * @param Requirement[] $required
     */
    private static function renderRequiredPHPVersionsSection(
        array $required,
        IO $io,
    ): void {
        if (0 === count($required)) {
            $io->writeln('<comment>No PHP requirement found.</comment>');

            return;
        }

        $io->writeln('<comment>Required PHP versions:</comment>');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => sprintf(
                    '  - %s (%s)',
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ),
                $required,
            ),
        );
    }

    /**
     * @param Requirement[] $required
     */
    private static function renderRequiredExtensionsSection(
        array $required,
        IO $io,
    ): void {
        if (0 === count($required)) {
            $io->writeln('<comment>No required extension found.</comment>');

            return;
        }

        $io->writeln('<comment>Required extensions:</comment>');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => sprintf(
                    '  - ext-%s (%s)',
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ),
                $required,
            ),
        );
    }

    /**
     * @param Requirement[] $provided
     */
    private static function renderProvidedExtensionsSection(
        array $provided,
        IO    $io,
    ): void {
        if (0 === count($provided)) {
            $io->writeln('<comment>No provided extension found.</comment>');

            return;
        }

        $io->writeln('<comment>Provided extensions:</comment>');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => sprintf(
                    '  - ext-%s (%s)',
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ),
                $provided,
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
        if (0 === count($required)) {
            $io->writeln('<comment>No required extension found.</comment>');

            return;
        }

        $io->writeln('<comment>Final required extensions:</comment> (accounts for the provided extensions)');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => sprintf(
                    '  - ext-%s (%s)',
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ),
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
            $io->writeln('<comment>No conflicting package found.</comment>');

            return;
        }

        $io->writeln('<comment>Conflicting extensions:</comment>');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => sprintf(
                    '  - ext-%s (%s)',
                    $requirement->condition,
                    $requirement->source ?? 'root',
                ),
                $conflicting,
            ),
        );
    }
}
