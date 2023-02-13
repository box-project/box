<?php declare(strict_types=1);











namespace Composer\Downloader;

use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\VcsRepository;
use Composer\Util\Perforce;




class PerforceDownloader extends VcsDownloader
{

protected $perforce;




protected function doDownload(PackageInterface $package, string $path, string $url, ?PackageInterface $prevPackage = null): PromiseInterface
{
return \React\Promise\resolve(null);
}




public function doInstall(PackageInterface $package, string $path, string $url): PromiseInterface
{
$ref = $package->getSourceReference();
$label = $this->getLabelFromSourceReference((string) $ref);

$this->io->writeError('Cloning ' . $ref);
$this->initPerforce($package, $path, $url);
$this->perforce->setStream($ref);
$this->perforce->p4Login();
$this->perforce->writeP4ClientSpec();
$this->perforce->connectClient();
$this->perforce->syncCodeBase($label);
$this->perforce->cleanupClientSpec();

return \React\Promise\resolve(null);
}

private function getLabelFromSourceReference(string $ref): ?string
{
$pos = strpos($ref, '@');
if (false !== $pos) {
return substr($ref, $pos + 1);
}

return null;
}

public function initPerforce(PackageInterface $package, string $path, string $url): void
{
if (!empty($this->perforce)) {
$this->perforce->initializePath($path);

return;
}

$repository = $package->getRepository();
$repoConfig = null;
if ($repository instanceof VcsRepository) {
$repoConfig = $this->getRepoConfig($repository);
}
$this->perforce = Perforce::create($repoConfig, $url, $path, $this->process, $this->io);
}




private function getRepoConfig(VcsRepository $repository): array
{
return $repository->getRepoConfig();
}




protected function doUpdate(PackageInterface $initial, PackageInterface $target, string $path, string $url): PromiseInterface
{
return $this->doInstall($target, $path, $url);
}




public function getLocalChanges(PackageInterface $package, string $path): ?string
{
$this->io->writeError('Perforce driver does not check for local changes before overriding');

return null;
}




protected function getCommitLogs(string $fromReference, string $toReference, string $path): string
{
return $this->perforce->getCommitLogs($fromReference, $toReference);
}

public function setPerforce(Perforce $perforce): void
{
$this->perforce = $perforce;
}




protected function hasMetadataRepository(string $path): bool
{
return true;
}
}
