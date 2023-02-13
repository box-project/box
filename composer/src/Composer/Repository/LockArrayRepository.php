<?php declare(strict_types=1);











namespace Composer\Repository;








class LockArrayRepository extends ArrayRepository
{
use CanonicalPackagesTrait;

public function getRepoName(): string
{
return 'lock repo';
}
}
