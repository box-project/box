<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Pcre\Preg;






class PackageRepository extends ArrayRepository
{

private $config;






public function __construct(array $config)
{
parent::__construct();
$this->config = $config['package'];


if (!is_numeric(key($this->config))) {
$this->config = [$this->config];
}
}




protected function initialize(): void
{
parent::initialize();

$loader = new ValidatingArrayLoader(new ArrayLoader(null, true), true);
foreach ($this->config as $package) {
try {
$package = $loader->load($package);
} catch (\Exception $e) {
throw new InvalidRepositoryException('A repository of type "package" contains an invalid package definition: '.$e->getMessage()."\n\nInvalid package definition:\n".json_encode($package));
}

$this->addPackage($package);
}
}

public function getRepoName(): string
{
return Preg::replace('{^array }', 'package ', parent::getRepoName());
}
}
