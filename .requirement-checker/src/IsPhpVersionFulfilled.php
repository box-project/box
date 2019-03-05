<?php

namespace _HumbugBoxbb220723f65b\KevinGH\RequirementChecker;

use _HumbugBoxbb220723f65b\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \_HumbugBoxbb220723f65b\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \_HumbugBoxbb220723f65b\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
