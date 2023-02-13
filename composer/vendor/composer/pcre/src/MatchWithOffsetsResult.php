<?php










namespace Composer\Pcre;

final class MatchWithOffsetsResult
{







public $matches;





public $matched;






public function __construct(int $count, array $matches)
{
$this->matches = $matches;
$this->matched = (bool) $count;
}
}
