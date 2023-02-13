<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Advisory\PartialSecurityAdvisory;
use Composer\Advisory\SecurityAdvisory;







interface AdvisoryProviderInterface
{
public function hasSecurityAdvisories(): bool;





public function getSecurityAdvisories(array $packageConstraintMap, bool $allowPartialAdvisories = false): array;
}
