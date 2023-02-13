<?php declare(strict_types=1);











namespace Composer\Repository;

interface VersionCacheInterface
{



public function getVersionPackage(string $version, string $identifier);
}
