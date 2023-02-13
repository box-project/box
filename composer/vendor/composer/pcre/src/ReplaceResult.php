<?php










namespace Composer\Pcre;

final class ReplaceResult
{




public $result;





public $count;





public $matched;




public function __construct(int $count, string $result)
{
$this->count = $count;
$this->matched = (bool) $count;
$this->result = $result;
}
}
