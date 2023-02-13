<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;




interface PolicyInterface
{



public function versionCompare(PackageInterface $a, PackageInterface $b, string $operator): bool;





public function selectPreferredPackages(Pool $pool, array $literals, ?string $requiredPackage = null): array;
}
