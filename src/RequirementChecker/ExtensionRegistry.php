<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\Composer\Package\Extension;
use function array_diff_key;
use function array_map;
use function array_unique;

final class ExtensionRegistry
{
    private array $requiredExtensions = [];
    private array $providedExtensions = [];
    private array $conflictingExtensions = [];

    public function addRequiredExtension(Extension $extension, string $source): void
    {
        $this->requiredExtensions[$extension->name][] = $source;
    }

    public function addProvidedExtension(Extension $extension, string $source): void
    {
        $this->providedExtensions[$extension->name][] = $source;
    }

    public function addConflictingExtension(Extension $extension, string $source): void
    {
        $this->conflictingExtensions[$extension->name][] = $source;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getRequiredExtensions(): array
    {
        return array_diff_key(
            array_map(
                array_unique(...),
                $this->requiredExtensions,
            ),
            $this->providedExtensions,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function getConflictingExtensions(): array
    {
        return $this->conflictingExtensions;
    }
}