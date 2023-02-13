<?php declare(strict_types=1);











namespace Composer\Util\Http;

use Composer\Json\JsonFile;
use Composer\Pcre\Preg;
use Composer\Util\HttpDownloader;




class Response
{

private $request;

private $code;

private $headers;

private $body;





public function __construct(array $request, ?int $code, array $headers, ?string $body)
{
if (!isset($request['url'])) { 
throw new \LogicException('url key missing from request array');
}
$this->request = $request;
$this->code = (int) $code;
$this->headers = $headers;
$this->body = $body;
}

public function getStatusCode(): int
{
return $this->code;
}

public function getStatusMessage(): ?string
{
$value = null;
foreach ($this->headers as $header) {
if (Preg::isMatch('{^HTTP/\S+ \d+}i', $header)) {


$value = $header;
}
}

return $value;
}




public function getHeaders(): array
{
return $this->headers;
}




public function getHeader(string $name): ?string
{
return self::findHeaderValue($this->headers, $name);
}




public function getBody(): ?string
{
return $this->body;
}




public function decodeJson()
{
return JsonFile::parseJson($this->body, $this->request['url']);
}




public function collect(): void
{

$this->request = $this->code = $this->headers = $this->body = null;
}





public static function findHeaderValue(array $headers, string $name): ?string
{
$value = null;
foreach ($headers as $header) {
if (Preg::isMatch('{^'.preg_quote($name).':\s*(.+?)\s*$}i', $header, $match)) {
$value = $match[1];
}
}

return $value;
}
}
