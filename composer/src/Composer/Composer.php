<?php declare(strict_types=1);











namespace Composer;

use Composer\Package\Locker;
use Composer\Pcre\Preg;
use Composer\Plugin\PluginManager;
use Composer\Downloader\DownloadManager;
use Composer\Autoload\AutoloadGenerator;
use Composer\Package\Archiver\ArchiveManager;






class Composer extends PartialComposer
{

























public const VERSION = '2.5.3';
public const BRANCH_ALIAS_VERSION = '';
public const RELEASE_DATE = '2023-02-10 13:23:52';
public const SOURCE_VERSION = '';










public const RUNTIME_API_VERSION = '2.2.2';

public static function getVersion(): string
{

if (self::VERSION === '@package_version'.'@') {
return self::SOURCE_VERSION;
}


if (self::BRANCH_ALIAS_VERSION !== '' && Preg::isMatch('{^[a-f0-9]{40}$}', self::VERSION)) {
return self::BRANCH_ALIAS_VERSION.'+'.self::VERSION;
}

return self::VERSION;
}




private $locker;




private $downloadManager;




private $pluginManager;




private $autoloadGenerator;




private $archiveManager;

public function setLocker(Locker $locker): void
{
$this->locker = $locker;
}

public function getLocker(): Locker
{
return $this->locker;
}

public function setDownloadManager(DownloadManager $manager): void
{
$this->downloadManager = $manager;
}

public function getDownloadManager(): DownloadManager
{
return $this->downloadManager;
}

public function setArchiveManager(ArchiveManager $manager): void
{
$this->archiveManager = $manager;
}

public function getArchiveManager(): ArchiveManager
{
return $this->archiveManager;
}

public function setPluginManager(PluginManager $manager): void
{
$this->pluginManager = $manager;
}

public function getPluginManager(): PluginManager
{
return $this->pluginManager;
}

public function setAutoloadGenerator(AutoloadGenerator $autoloadGenerator): void
{
$this->autoloadGenerator = $autoloadGenerator;
}

public function getAutoloadGenerator(): AutoloadGenerator
{
return $this->autoloadGenerator;
}
}
