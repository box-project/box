<?php declare(strict_types=1);











namespace Composer\DependencyResolver\Operation;

use Composer\Package\PackageInterface;






class InstallOperation extends SolverOperation implements OperationInterface
{
protected const TYPE = 'install';




protected $package;

public function __construct(PackageInterface $package)
{
$this->package = $package;
}




public function getPackage(): PackageInterface
{
return $this->package;
}




public function show($lock): string
{
return self::format($this->package, $lock);
}

public static function format(PackageInterface $package, bool $lock = false): string
{
return ($lock ? 'Locking ' : 'Installing ').'<info>'.$package->getPrettyName().'</info> (<comment>'.$package->getFullPrettyVersion().'</comment>)';
}
}
