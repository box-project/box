<?php declare(strict_types=1);











namespace Composer\Repository;








interface InstalledRepositoryInterface extends WritableRepositoryInterface
{



public function getDevMode();




public function isFresh();
}
