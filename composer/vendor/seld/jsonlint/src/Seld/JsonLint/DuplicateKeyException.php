<?php










namespace Seld\JsonLint;

class DuplicateKeyException extends ParsingException
{




protected $details;






public function __construct($message, $key, array $details)
{
$details['key'] = $key;
parent::__construct($message, $details);
}




public function getKey()
{
return $this->details['key'];
}




public function getDetails()
{
return $this->details;
}
}
