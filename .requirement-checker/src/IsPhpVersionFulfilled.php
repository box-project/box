<?php

namespace HumbugBox370\KevinGH\RequirementChecker;

use HumbugBox370\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \HumbugBox370\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \HumbugBox370\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
