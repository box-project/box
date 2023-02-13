<?php










namespace Composer\Pcre;

final class MatchAllWithOffsetsResult
{







public $matches;





public $count;





public $matched;






public function __construct(int $count, array $matches)
{
$this->matches = $matches;
$this->matched = (bool) $count;
$this->count = $count;
}
}
