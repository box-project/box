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
use function array_unique;
use function natsort;
use function strnatcmp;
use function uksort;

final class RequirementsBuilder
{
    private array $predefinedRequirements = [];
    private array $requiredExtensions = [];
    private array $providedExtensions = [];
    private array $conflictingExtensions = [];

    public function addRequirement(Requirement $requirement): void
    {
        $this->predefinedRequirements[] = $requirement;
    }

    public function addRequiredExtension(Extension $extension, ?string $source): void
    {
        $this->requiredExtensions[$extension->name][] = $source;
    }

    public function addProvidedExtension(Extension $extension, ?string $source): void
    {
        $this->providedExtensions[$extension->name][] = $source;
    }

    public function addConflictingExtension(Extension $extension, ?string $source): void
    {
        $this->conflictingExtensions[$extension->name][] = $source;
    }

    public function build(): Requirements
    {
        $requirements = $this->predefinedRequirements;

        foreach ($this->getSortedRequiredExtensions() as $extensionName => $sources) {
            $sortedDistinctSources = self::createSortedDistinctList($sources);

            foreach ($sortedDistinctSources as $source) {
                $requirements[] = Requirement::forRequiredExtension(
                    $extensionName,
                    $source,
                );
            }
        }

        foreach ($this->getSortedConflictedExtensions() as $extensionName => $sources) {
            $sortedDistinctSources = self::createSortedDistinctList($sources);

            foreach ($sortedDistinctSources as $source) {
                $requirements[] = Requirement::forConflictingExtension(
                    $extensionName,
                    $source,
                );
            }
        }

        return new Requirements($requirements);
    }

    /**
     * @return array<string, list<string>>
     */
    private function getSortedRequiredExtensions(): array
    {
        $extensions = array_diff_key(
            $this->requiredExtensions,
            $this->providedExtensions,
        );

        uksort($extensions, strnatcmp(...));

        return $extensions;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getSortedConflictedExtensions(): array
    {
        $extensions = $this->conflictingExtensions;

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
