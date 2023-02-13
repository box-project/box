<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryInterface;





class LocalRepoTransaction extends Transaction
{
public function __construct(RepositoryInterface $lockedRepository, InstalledRepositoryInterface $localRepository)
{
parent::__construct(
$localRepository->getPackages(),
$lockedRepository->getPackages()
);
}
}
