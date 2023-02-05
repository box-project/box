<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

/**
 * @private
 */
final class PackageInfo
{
    public function __construct(private array $packageInfo)
    {
    }

    public function getName(): string
    {
        return $this->packageInfo['name'];
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->packageInfo['require']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }
}
