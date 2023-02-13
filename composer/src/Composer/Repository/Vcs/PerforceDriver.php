<?php declare(strict_types=1);











namespace Composer\Repository\Vcs;

use Composer\Config;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\ProcessExecutor;
use Composer\Util\Perforce;
use Composer\Util\Http\Response;




class PerforceDriver extends VcsDriver
{

protected $depot;

protected $branch;

protected $perforce = null;




public function initialize(): void
{
$this->depot = $this->repoConfig['depot'];
$this->branch = '';
if (!empty($this->repoConfig['branch'])) {
$this->branch = $this->repoConfig['branch'];
}

$this->initPerforce($this->repoConfig);
$this->perforce->p4Login();
$this->perforce->checkStream();

$this->perforce->writeP4ClientSpec();
$this->perforce->connectClient();
}




private function initPerforce(array $repoConfig): void
{
if (!empty($this->perforce)) {
return;
}

if (!Cache::isUsable($this->config->get('cache-vcs-dir'))) {
throw new \RuntimeException('PerforceDriver requires a usable cache directory, and it looks like you set it to be disabled');
}

$repoDir = $this->config->get('cache-vcs-dir') . '/' . $this->depot;
$this->perforce = Perforce::create($repoConfig, $this->getUrl(), $repoDir, $this->process, $this->io);
}




public function getFileContent(string $file, string $identifier): ?string
{
return $this->perforce->getFileContent($file, $identifier);
}




public function getChangeDate(string $identifier): ?\DateTimeImmutable
{
return null;
}




public function getRootIdentifier(): string
{
return $this->branch;
}




public function getBranches(): array
{
return $this->perforce->getBranches();
}




public function getTags(): array
{
return $this->perforce->getTags();
}




public function getDist(string $identifier): ?array
{
return null;
}




public function getSource(string $identifier): array
{
return [
'type' => 'perforce',
'url' => $this->repoConfig['url'],
'reference' => $identifier,
'p4user' => $this->perforce->getUser(),
];
}




public function getUrl(): string
{
return $this->url;
}




public function hasComposerFile(string $identifier): bool
{
$composerInfo = $this->perforce->getComposerInformation('//' . $this->depot . '/' . $identifier);

return !empty($composerInfo);
}




public function getContents(string $url): Response
{
throw new \BadMethodCallException('Not implemented/used in PerforceDriver');
}




public static function supports(IOInterface $io, Config $config, string $url, bool $deep = false): bool
{
if ($deep || Preg::isMatch('#\b(perforce|p4)\b#i', $url)) {
return Perforce::checkServerExists($url, new ProcessExecutor($io));
}

return false;
}




public function cleanup(): void
{
$this->perforce->cleanupClientSpec();
$this->perforce = null;
}

public function getDepot(): string
{
return $this->depot;
}

public function getBranch(): string
{
return $this->branch;
}
}
