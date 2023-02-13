<?php declare(strict_types=1);











namespace Composer\Downloader;




class TransportException extends \RuntimeException
{

protected $headers;

protected $response;

protected $statusCode;

protected $responseInfo = [];




public function setHeaders(array $headers): void
{
$this->headers = $headers;
}




public function getHeaders(): ?array
{
return $this->headers;
}

public function setResponse(?string $response): void
{
$this->response = $response;
}




public function getResponse(): ?string
{
return $this->response;
}




public function setStatusCode($statusCode): void
{
$this->statusCode = $statusCode;
}




public function getStatusCode(): ?int
{
return $this->statusCode;
}




public function getResponseInfo(): array
{
return $this->responseInfo;
}




public function setResponseInfo(array $responseInfo): void
{
$this->responseInfo = $responseInfo;
}
}
