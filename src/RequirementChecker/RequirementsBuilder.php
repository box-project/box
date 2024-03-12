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

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\Composer\Package\Extension;
use function array_diff_key;
use function array_map;
use function array_unique;
use function natsort;
use function strcmp;
use function strnatcmp;
use function uksort;
use function usort;

final class RequirementsBuilder
{
    /**
     * @var list<Requirement>
     */
    private array $predefinedRequirements = [];

    /**
     * @var array<string, array<string|null>>
     */
    private array $requiredExtensions = [];

    /**
     * @var array<string, array<string|null>>
     */
    private array $providedExtensions = [];

    /**
     * @var array<string, array{string|null, RequirementType}>
     */
    private array $allExtensions = [];

    /**
     * @var array<string, array<string|null>>
     */
    private array $conflictingExtensions = [];

    public function addRequirement(Requirement $requirement): void
    {
        $this->predefinedRequirements[] = $requirement;
    }

    public function addRequiredExtension(Extension $extension, ?string $source): void
    {
        $this->requiredExtensions[$extension->name][] = $source;
        $this->allExtensions[$extension->name][$source] = [$source, RequirementType::EXTENSION];
    }

    public function addProvidedExtension(Extension $extension, ?string $source): void
    {
        $this->providedExtensions[$extension->name][] = $source;
        $this->allExtensions[$extension->name][$source] = [$source, RequirementType::PROVIDED_EXTENSION];
    }

    public function addConflictingExtension(Extension $extension, ?string $source): void
    {
        $this->conflictingExtensions[$extension->name][] = $source;
    }

    public function all(): Requirements
    {
        $requirements = $this->predefinedRequirements;

        foreach ($this->getSortedRequiredAndProvidedExtensions() as $extensionName => $sources) {
            foreach ($sources as [$source, $type]) {
                $requirements[] = RequirementType::EXTENSION === $type
                    ? Requirement::forRequiredExtension(
                        $extensionName,
                        $source,
                    )
                    : Requirement::forProvidedExtension(
                        $extensionName,
                        $source,
                    );
            }
        }

        foreach ($this->getSortedConflictedExtensions() as $extensionName => $sources) {
            foreach ($sources as $source) {
                $requirements[] = Requirement::forConflictingExtension(
                    $extensionName,
                    $source,
                );
            }
        }

        return new Requirements($requirements);
    }

    public function build(): Requirements
    {
        $requirements = $this->predefinedRequirements;

        foreach ($this->getSortedRequiredExtensions() as $extensionName => $sources) {
            foreach ($sources as $source) {
                $requirements[] = Requirement::forRequiredExtension(
                    $extensionName,
                    $source,
                );
            }
        }

        foreach ($this->getSortedConflictedExtensions() as $extensionName => $sources) {
            foreach ($sources as $source) {
                $requirements[] = Requirement::forConflictingExtension(
                    $extensionName,
                    $source,
                );
            }
        }

        return new Requirements($requirements);
    }

    /**
     * @return array<string, list<array{string|null, RequirementType}>>
     */
    private function getSortedRequiredAndProvidedExtensions(): array
    {
        return array_map(
            static function (array $sources): array {
                usort(
                    $sources,
                    static fn (array $sourceTypePairA, array $sourceTypePairB) => strcmp(
                        (string) $sourceTypePairA[0],
                        (string) $sourceTypePairB[0],
                    ),
                );

                return $sources;
            },
            self::sortByExtensionName($this->allExtensions),
        );
    }

    /**
     * @return array<string, list<string|null>>
     */
    private function getSortedRequiredExtensions(): array
    {
        return array_map(
            self::createSortedDistinctList(...),
            self::sortByExtensionName(
                array_diff_key(
                    $this->requiredExtensions,
                    $this->providedExtensions,
                ),
            ),
        );
    }

    /**
     * @return array<string, list<string|null>>
     */
    private function getSortedConflictedExtensions(): array
    {
        return array_map(
            self::createSortedDistinctList(...),
            self::sortByExtensionName($this->conflictingExtensions),
        );
    }

    /**
     * @template T
     *
     * @param  array<string, T> $extensions
     * @return array<string, T>
     */
    private static function sortByExtensionName(array $extensions): array
    {
        uksort($extensions, strnatcmp(...));

        return $extensions;
    }

    /**
     * @param array<string|null> $sources
     *
     * @return list<string|null>
     */
    private static function createSortedDistinctList(array $sources): array
    {
        $uniqueSources = array_unique($sources);

        natsort($uniqueSources);

        return $uniqueSources;
    }
}
