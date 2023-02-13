<?php declare(strict_types=1);











namespace Composer\Plugin;

use Composer\EventDispatcher\Event;
use Composer\Util\HttpDownloader;






class PreFileDownloadEvent extends Event
{



private $httpDownloader;




private $processedUrl;




private $customCacheKey;




private $type;




private $context;




private $transportOptions = [];








public function __construct(string $name, HttpDownloader $httpDownloader, string $processedUrl, string $type, $context = null)
{
parent::__construct($name);
$this->httpDownloader = $httpDownloader;
$this->processedUrl = $processedUrl;
$this->type = $type;
$this->context = $context;
}

public function getHttpDownloader(): HttpDownloader
{
return $this->httpDownloader;
}






public function getProcessedUrl(): string
{
return $this->processedUrl;
}






public function setProcessedUrl(string $processedUrl): void
{
$this->processedUrl = $processedUrl;
}




public function getCustomCacheKey(): ?string
{
return $this->customCacheKey;
}






public function setCustomCacheKey(?string $customCacheKey): void
{
$this->customCacheKey = $customCacheKey;
}




public function getType(): string
{
return $this->type;
}









public function getContext()
{
return $this->context;
}








public function getTransportOptions(): array
{
return $this->transportOptions;
}








public function setTransportOptions(array $options): void
{
$this->transportOptions = $options;
}
}
