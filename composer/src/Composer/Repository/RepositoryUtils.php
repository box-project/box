<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\PackageInterface;






class RepositoryUtils
{










public static function filterRequiredPackages(array $packages, PackageInterface $requirer, array $bucket = []): array
{
$requires = $requirer->getRequires();

foreach ($packages as $candidate) {
foreach ($candidate->getNames() as $name) {
if (isset($requires[$name])) {
if (!in_array($candidate, $bucket, true)) {
$bucket[] = $candidate;
$bucket = self::filterRequiredPackages($packages, $candidate, $bucket);
}
break;
}
}
}

return $bucket;
}






public static function flattenRepositories(RepositoryInterface $repo, bool $unwrapFilterRepos = true): array
{

if ($unwrapFilterRepos && $repo instanceof FilterRepository) {
$repo = $repo->getRepository();
}

if (!$repo instanceof CompositeRepository) {
return [$repo];
}

$repos = [];
foreach ($repo->getRepositories() as $r) {
foreach (self::flattenRepositories($r, $unwrapFilterRepos) as $r2) {
$repos[] = $r2;
}
}

return $repos;
}
}
