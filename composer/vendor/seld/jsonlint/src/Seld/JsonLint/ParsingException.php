<?php










namespace Seld\JsonLint;

class ParsingException extends \Exception
{



protected $details;





public function __construct($message, $details = array())
{
$this->details = $details;
parent::__construct($message);
}




public function getDetails()
{
return $this->details;
}
}
