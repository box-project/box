<?php declare(strict_types=1);











namespace Composer\DependencyResolver;





class Rule2Literals extends Rule
{

protected $literal1;

protected $literal2;







public function __construct(int $literal1, int $literal2, $reason, $reasonData)
{
parent::__construct($reason, $reasonData);

if ($literal1 < $literal2) {
$this->literal1 = $literal1;
$this->literal2 = $literal2;
} else {
$this->literal1 = $literal2;
$this->literal2 = $literal1;
}
}




public function getLiterals(): array
{
return [$this->literal1, $this->literal2];
}




public function getHash()
{
return $this->literal1.','.$this->literal2;
}









public function equals(Rule $rule): bool
{

if ($rule instanceof self) {
if ($this->literal1 !== $rule->literal1) {
return false;
}

if ($this->literal2 !== $rule->literal2) {
return false;
}

return true;
}

$literals = $rule->getLiterals();
if (2 !== \count($literals)) {
return false;
}

if ($this->literal1 !== $literals[0]) {
return false;
}

if ($this->literal2 !== $literals[1]) {
return false;
}

return true;
}


public function isAssertion(): bool
{
return false;
}




public function __toString(): string
{
$result = $this->isDisabled() ? 'disabled(' : '(';

$result .= $this->literal1 . '|' . $this->literal2 . ')';

return $result;
}
}
