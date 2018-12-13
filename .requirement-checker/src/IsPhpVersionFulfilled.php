<?php

namespace _HumbugBoxaa731ba336da\KevinGH\RequirementChecker;

use _HumbugBoxaa731ba336da\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \_HumbugBoxaa731ba336da\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \_HumbugBoxaa731ba336da\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
