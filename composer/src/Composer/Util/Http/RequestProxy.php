<?php declare(strict_types=1);











namespace Composer\Util\Http;

use Composer\Util\Url;





class RequestProxy
{

private $contextOptions;

private $isSecure;

private $formattedUrl;

private $url;




public function __construct(string $url, array $contextOptions, string $formattedUrl)
{
$this->url = $url;
$this->contextOptions = $contextOptions;
$this->formattedUrl = $formattedUrl;
$this->isSecure = 0 === strpos($url, 'https://');
}






public function getContextOptions(): array
{
return $this->contextOptions;
}







public function getFormattedUrl(?string $format = ''): string
{
$result = '';
if ($this->formattedUrl) {
$format = $format ?: '%s';
$result = sprintf($format, $this->formattedUrl);
}

return $result;
}






public function getUrl(): string
{
return $this->url;
}






public function isSecure(): bool
{
return $this->isSecure;
}
}
