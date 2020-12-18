<?php

namespace HumbugBox3100\KevinGH\RequirementChecker;

use HumbugBox3100\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \HumbugBox3100\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \HumbugBox3100\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
