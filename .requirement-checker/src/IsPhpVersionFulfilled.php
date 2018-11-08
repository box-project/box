<?php

namespace _HumbugBoxacafcfe30294\KevinGH\RequirementChecker;

use _HumbugBoxacafcfe30294\Composer\Semver\Semver;
final class IsPhpVersionFulfilled implements \_HumbugBoxacafcfe30294\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct($requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke()
    {
        return \_HumbugBoxacafcfe30294\Composer\Semver\Semver::satisfies(\sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
