<?php

namespace _HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker;

use _HumbugBoxc5a6d13bc633\Composer\Semver\Semver;
/**
@private
*/
final class IsPhpVersionFulfilled implements \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IsFulfilled
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
        return \_HumbugBoxc5a6d13bc633\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
