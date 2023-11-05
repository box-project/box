<?php

declare (strict_types=1);
namespace HumbugBox451\KevinGH\RequirementChecker;

use HumbugBox451\Composer\Semver\Semver;
use function sprintf;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;
/** @internal */
final class IsPhpVersionFulfilled implements IsFulfilled
{
    private $requiredPhpVersion;
    public function __construct(string $requiredPhpVersion)
    {
        $this->requiredPhpVersion = $requiredPhpVersion;
    }
    public function __invoke() : bool
    {
        return Semver::satisfies(sprintf('%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION), $this->requiredPhpVersion);
    }
}
