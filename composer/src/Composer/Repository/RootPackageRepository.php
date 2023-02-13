<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\RootPackageInterface;








class RootPackageRepository extends ArrayRepository
{
public function __construct(RootPackageInterface $package)
{
parent::__construct([$package]);
}

public function getRepoName(): string
{
return 'root package repo';
}
}
