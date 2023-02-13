<?php declare(strict_types=1);











namespace Composer\Util\Http;




class CurlResponse extends Response
{





private $curlInfo;




public function __construct(array $request, ?int $code, array $headers, ?string $body, array $curlInfo)
{
parent::__construct($request, $code, $headers, $body);
$this->curlInfo = $curlInfo;
}




public function getCurlInfo(): array
{
return $this->curlInfo;
}
}
