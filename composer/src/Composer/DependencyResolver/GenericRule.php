<?php declare(strict_types=1);











namespace Composer\DependencyResolver;




class GenericRule extends Rule
{

protected $literals;




public function __construct(array $literals, $reason, $reasonData)
{
parent::__construct($reason, $reasonData);


sort($literals);

$this->literals = $literals;
}




public function getLiterals(): array
{
return $this->literals;
}




public function getHash()
{
$data = unpack('ihash', md5(implode(',', $this->literals), true));

return $data['hash'];
}









public function equals(Rule $rule): bool
{
return $this->literals === $rule->getLiterals();
}

public function isAssertion(): bool
{
return 1 === \count($this->literals);
}




public function __toString(): string
{
$result = $this->isDisabled() ? 'disabled(' : '(';

foreach ($this->literals as $i => $literal) {
if ($i !== 0) {
$result .= '|';
}
$result .= $literal;
}

$result .= ')';

return $result;
}
}
