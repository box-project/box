<?php

namespace _HumbugBoxf93f74f93d56\KevinGH\RequirementChecker;

use _HumbugBoxf93f74f93d56\Composer\Semver\Semver;
/**
@private
*/
final class IsPhpVersionFulfilled implements \_HumbugBoxf93f74f93d56\KevinGH\RequirementChecker\IsFulfilled
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
        return \_HumbugBoxf93f74f93d56\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
