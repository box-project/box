<?php declare(strict_types=1);











namespace Composer\Repository;

use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\LoaderInterface;
use Composer\Util\Tar;
use Composer\Util\Zip;




class ArtifactRepository extends ArrayRepository implements ConfigurableRepositoryInterface
{

protected $loader;


protected $lookup;

protected $repoConfig;

private $io;




public function __construct(array $repoConfig, IOInterface $io)
{
parent::__construct();
if (!extension_loaded('zip')) {
throw new \RuntimeException('The artifact repository requires PHP\'s zip extension');
}

$this->loader = new ArrayLoader();
$this->lookup = $repoConfig['url'];
$this->io = $io;
$this->repoConfig = $repoConfig;
}

public function getRepoName()
{
return 'artifact repo ('.$this->lookup.')';
}

public function getRepoConfig()
{
return $this->repoConfig;
}

protected function initialize()
{
parent::initialize();

$this->scanDirectory($this->lookup);
}

private function scanDirectory(string $path): void
{
$io = $this->io;

$directory = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
$iterator = new \RecursiveIteratorIterator($directory);
$regex = new \RegexIterator($iterator, '/^.+\.(zip|tar|gz|tgz)$/i');
foreach ($regex as $file) {

if (!$file->isFile()) {
continue;
}

$package = $this->getComposerInformation($file);
if (!$package) {
$io->writeError("File <comment>{$file->getBasename()}</comment> doesn't seem to hold a package", true, IOInterface::VERBOSE);
continue;
}

$template = 'Found package <info>%s</info> (<comment>%s</comment>) in file <info>%s</info>';
$io->writeError(sprintf($template, $package->getName(), $package->getPrettyVersion(), $file->getBasename()), true, IOInterface::VERBOSE);

$this->addPackage($package);
}
}




private function getComposerInformation(\SplFileInfo $file): ?BasePackage
{
$json = null;
$fileType = null;
$fileExtension = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
if (in_array($fileExtension, ['gz', 'tar', 'tgz'], true)) {
$fileType = 'tar';
} elseif ($fileExtension === 'zip') {
$fileType = 'zip';
} else {
throw new \RuntimeException('Files with "'.$fileExtension.'" extensions aren\'t supported. Only ZIP and TAR/TAR.GZ/TGZ archives are supported.');
}

try {
if ($fileType === 'tar') {
$json = Tar::getComposerJson($file->getPathname());
} else {
$json = Zip::getComposerJson($file->getPathname());
}
} catch (\Exception $exception) {
$this->io->write('Failed loading package '.$file->getPathname().': '.$exception->getMessage(), false, IOInterface::VERBOSE);
}

if (null === $json) {
return null;
}

$package = JsonFile::parseJson($json, $file->getPathname().'#composer.json');
$package['dist'] = [
'type' => $fileType,
'url' => strtr($file->getPathname(), '\\', '/'),
'shasum' => sha1_file($file->getRealPath()),
];

try {
$package = $this->loader->load($package);
} catch (\UnexpectedValueException $e) {
throw new \UnexpectedValueException('Failed loading package in '.$file.': '.$e->getMessage(), 0, $e);
}

return $package;
}
}
