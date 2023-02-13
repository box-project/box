<?php declare(strict_types=1);











namespace Composer\Repository;








class InstalledArrayRepository extends WritableArrayRepository implements InstalledRepositoryInterface
{
public function getRepoName(): string
{
return 'installed '.parent::getRepoName();
}




public function isFresh(): bool
{


return $this->count() === 0;
}
}
