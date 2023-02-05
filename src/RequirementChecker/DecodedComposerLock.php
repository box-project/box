<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use function array_map;

/**
 * @private
 */
final class DecodedComposerLock
{
    /**
     * @param array $composerLockDecodedContents Decoded JSON contents of the `composer.lock` file
     */
    public function __construct(private array $composerLockDecodedContents)
    {
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->composerLockDecodedContents['platform']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }

    /**
     * @return list<PackageInfo>
     */
    public function getPackages(): array
    {
        return array_map(
            PackageInfo::__construct(...),
            $this->composerLockDecodedContents['packages'] ?? [],
        );
    }
}
