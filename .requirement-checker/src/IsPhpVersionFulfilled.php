<?php

namespace _HumbugBox87c495005ea2\KevinGH\RequirementChecker;

use _HumbugBox87c495005ea2\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \_HumbugBox87c495005ea2\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \_HumbugBox87c495005ea2\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
