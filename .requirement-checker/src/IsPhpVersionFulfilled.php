<?php

namespace HumbugBox3141\KevinGH\RequirementChecker;

use HumbugBox3141\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
