<?php declare(strict_types=1);











namespace Composer\DependencyResolver\Operation;

use Composer\Package\PackageInterface;






class UninstallOperation extends SolverOperation implements OperationInterface
{
protected const TYPE = 'uninstall';




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
return 'Removing <info>'.$package->getPrettyName().'</info> (<comment>'.$package->getFullPrettyVersion().'</comment>)';
}
}
