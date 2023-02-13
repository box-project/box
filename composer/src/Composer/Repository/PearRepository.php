<?php declare(strict_types=1);











namespace Composer\Repository;












class PearRepository extends ArrayRepository
{
public function __construct()
{
throw new \InvalidArgumentException('The PEAR repository has been removed from Composer 2.x');
}
}
