<?php declare(strict_types=1);











namespace Composer\Json;

use Exception;




class JsonValidationException extends Exception
{



protected $errors;




public function __construct(string $message, array $errors = [], ?Exception $previous = null)
{
$this->errors = $errors;
parent::__construct((string) $message, 0, $previous);
}




public function getErrors(): array
{
return $this->errors;
}
}
