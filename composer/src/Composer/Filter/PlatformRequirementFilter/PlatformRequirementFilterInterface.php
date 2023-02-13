<?php declare(strict_types=1);











namespace Composer\Filter\PlatformRequirementFilter;

interface PlatformRequirementFilterInterface
{
public function isIgnored(string $req): bool;
}
