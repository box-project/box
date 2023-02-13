<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\PackageInterface;
use Composer\Package\BasePackage;
use Composer\Semver\Constraint\ConstraintInterface;








interface RepositoryInterface extends \Countable
{
public const SEARCH_FULLTEXT = 0;
public const SEARCH_NAME = 1;
public const SEARCH_VENDOR = 2;








public function hasPackage(PackageInterface $package);









public function findPackage(string $name, $constraint);









public function findPackages(string $name, $constraint = null);






public function getPackages();

















public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags, array $alreadyLoaded = []);











public function search(string $query, int $mode = 0, ?string $type = null);











public function getProviders(string $packageName);








public function getRepoName();
}
