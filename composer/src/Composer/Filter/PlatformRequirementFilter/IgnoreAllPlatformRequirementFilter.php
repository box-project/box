<?php declare(strict_types=1);











namespace Composer\Filter\PlatformRequirementFilter;

use Composer\Repository\PlatformRepository;

final class IgnoreAllPlatformRequirementFilter implements PlatformRequirementFilterInterface
{
public function isIgnored(string $req): bool
{
return PlatformRepository::isPlatformPackage($req);
}
}
