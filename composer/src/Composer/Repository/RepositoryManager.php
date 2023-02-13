<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\IO\IOInterface;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Package\PackageInterface;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;








class RepositoryManager
{

private $localRepository;

private $repositories = [];

private $repositoryClasses = [];

private $io;

private $config;

private $httpDownloader;

private $eventDispatcher;

private $process;

public function __construct(IOInterface $io, Config $config, HttpDownloader $httpDownloader, ?EventDispatcher $eventDispatcher = null, ?ProcessExecutor $process = null)
{
$this->io = $io;
$this->config = $config;
$this->httpDownloader = $httpDownloader;
$this->eventDispatcher = $eventDispatcher;
$this->process = $process ?? new ProcessExecutor($io);
}







public function findPackage(string $name, $constraint): ?PackageInterface
{
foreach ($this->repositories as $repository) {

if ($package = $repository->findPackage($name, $constraint)) {
return $package;
}
}

return null;
}









public function findPackages(string $name, $constraint): array
{
$packages = [];

foreach ($this->getRepositories() as $repository) {
$packages = array_merge($packages, $repository->findPackages($name, $constraint));
}

return $packages;
}






public function addRepository(RepositoryInterface $repository): void
{
$this->repositories[] = $repository;
}








public function prependRepository(RepositoryInterface $repository): void
{
array_unshift($this->repositories, $repository);
}









public function createRepository(string $type, array $config, ?string $name = null): RepositoryInterface
{
if (!isset($this->repositoryClasses[$type])) {
throw new \InvalidArgumentException('Repository type is not registered: '.$type);
}

if (isset($config['packagist']) && false === $config['packagist']) {
$this->io->writeError('<warning>Repository "'.$name.'" ('.json_encode($config).') has a packagist key which should be in its own repository definition</warning>');
}

$class = $this->repositoryClasses[$type];

if (isset($config['only']) || isset($config['exclude']) || isset($config['canonical'])) {
$filterConfig = $config;
unset($config['only'], $config['exclude'], $config['canonical']);
}

$repository = new $class($config, $this->io, $this->config, $this->httpDownloader, $this->eventDispatcher, $this->process);

if (isset($filterConfig)) {
$repository = new FilterRepository($repository, $filterConfig);
}

return $repository;
}







public function setRepositoryClass(string $type, $class): void
{
$this->repositoryClasses[$type] = $class;
}






public function getRepositories(): array
{
return $this->repositories;
}






public function setLocalRepository(InstalledRepositoryInterface $repository): void
{
$this->localRepository = $repository;
}




public function getLocalRepository(): InstalledRepositoryInterface
{
return $this->localRepository;
}
}
