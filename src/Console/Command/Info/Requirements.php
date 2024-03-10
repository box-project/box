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
use KevinGH\Box\Composer\Artifact\DecodedComposerJson;
use KevinGH\Box\Composer\Artifact\DecodedComposerLock;
use KevinGH\Box\Configuration\Configuration;
use KevinGH\Box\Console\Command\ChangeWorkingDirOption;
use KevinGH\Box\Console\Command\ConfigOption;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\Requirement;
use KevinGH\Box\RequirementChecker\RequirementType;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use function array_map;
use function implode;
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

        $requirements = $this->factory->create(
            $composerJson,
            $composerLock,
            $config->getCompressionAlgorithm(),
        );

        [$required, $conflicting] = self::retrieveRequirements($requirements);

        self::renderRequiredSection($required, $io);
        self::renderConflictingSection($conflicting, $io);

        return ExitCode::SUCCESS;
    }

    /**
     * @return array{Requirement[], Requirement[]}
     */
    private static function retrieveRequirements(\KevinGH\Box\RequirementChecker\Requirements $requirements): array
    {
        [$required, $conflicting] = array_reduce(
            toArray($requirements),
            static function ($carry, Requirement $requirement): array {
                $hash = implode(
                    ':',
                    [
                        $requirement->type->value,
                        $requirement->condition,
                        $requirement->source,
                    ],
                );

                if (RequirementType::EXTENSION_CONFLICT === $requirement->type) {
                    $carry[1][$hash] = $requirement;
                } else {
                    $carry[0][$hash] = $requirement;
                }

                return $carry;
            },
            [[], []],
        );

        return [
            array_values($required),
            array_values($conflicting),
        ];
    }

    /**
     * @param Requirement[] $required
     */
    private static function renderRequiredSection(
        array $required,
        IO $io,
    ): void {
        if (0 === count($required)) {
            return;
        }

        $io->writeln('  <comment>Required:</comment>');
        $io->writeln(
            array_map(
                static fn (Requirement $requirement) => match ($requirement->type) {
                    RequirementType::PHP => sprintf(
                        '  - PHP %s (%s)',
                        $requirement->condition,
                        $requirement->source ?? 'root',
                    ),
                    RequirementType::EXTENSION => sprintf(
                        '  - ext-%s (%s)',
                        $requirement->condition,
                        $requirement->source ?? 'root',
                    ),
                },
                $required,
            ),
        );
    }

    /**
     * @param Requirement[] $conflicting
     */
    private static function renderConflictingSection(
        array $conflicting,
        IO $io,
    ): void {
        if (0 === count($conflicting)) {
            return;
        }

        $io->writeln('  <comment>Conflict:</comment>');
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
