<?php

namespace _HumbugBox5b963fb2bb9ba\KevinGH\RequirementChecker;

use _HumbugBox5b963fb2bb9ba\Composer\Semver\Semver;
/**
@private
*/
final class IsPhpVersionFulfilled implements \_HumbugBox5b963fb2bb9ba\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    /**
    @param
    */
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \_HumbugBox5b963fb2bb9ba\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
