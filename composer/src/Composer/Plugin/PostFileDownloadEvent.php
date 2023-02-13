<?php declare(strict_types=1);











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Composer\Package\PackageInterface;






class PostFileDownloadEvent extends Event
{



private $fileName;




private $checksum;




private $url;




private $context;




private $type;











public function __construct(string $name, ?string $fileName, ?string $checksum, string $url, string $type, $context = null)
{

if ($context === null && $type instanceof PackageInterface) {
$context = $type;
$type = 'package';
trigger_error('PostFileDownloadEvent::__construct should receive a $type=package and the package object in $context since Composer 2.1.', E_USER_DEPRECATED);
}

parent::__construct($name);
$this->fileName = $fileName;
$this->checksum = $checksum;
$this->url = $url;
$this->context = $context;
$this->type = $type;
}






public function getFileName(): ?string
{
return $this->fileName;
}




public function getChecksum(): ?string
{
return $this->checksum;
}




public function getUrl(): string
{
return $this->url;
}









public function getContext()
{
return $this->context;
}









public function getPackage(): ?PackageInterface
{
trigger_error('PostFileDownloadEvent::getPackage is deprecated since Composer 2.1, use getContext instead.', E_USER_DEPRECATED);
$context = $this->getContext();

return $context instanceof PackageInterface ? $context : null;
}




public function getType(): string
{
return $this->type;
}
}
