<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

/**
 * @private
 */
final class DecodedComposerJson
{
    /**
     * @param array $composerJsonDecodedContents Decoded JSON contents of the `composer.json` file
     */
    public function __construct(private array $composerJsonDecodedContents)
    {
    }

    public function getRequiredPhpVersion(): ?string
    {
        return $this->composerJsonDecodedContents['require']['php'] ?? null;
    }

    public function hasRequiredPhpVersion(): bool
    {
        return null !== $this->getRequiredPhpVersion();
    }
}
