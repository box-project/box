<?php declare(strict_types=1);











namespace Composer\DependencyResolver;





class RuleSetIterator implements \Iterator
{

protected $rules;

protected $types;


protected $currentOffset;

protected $currentType;

protected $currentTypeOffset;




public function __construct(array $rules)
{
$this->rules = $rules;
$this->types = array_keys($rules);
sort($this->types);

$this->rewind();
}

public function current(): Rule
{
return $this->rules[$this->currentType][$this->currentOffset];
}




public function key(): int
{
return $this->currentType;
}

public function next(): void
{
$this->currentOffset++;

if (!isset($this->rules[$this->currentType])) {
return;
}

if ($this->currentOffset >= \count($this->rules[$this->currentType])) {
$this->currentOffset = 0;

do {
$this->currentTypeOffset++;

if (!isset($this->types[$this->currentTypeOffset])) {
$this->currentType = -1;
break;
}

$this->currentType = $this->types[$this->currentTypeOffset];
} while (0 === \count($this->rules[$this->currentType]));
}
}

public function rewind(): void
{
$this->currentOffset = 0;

$this->currentTypeOffset = -1;
$this->currentType = -1;

do {
$this->currentTypeOffset++;

if (!isset($this->types[$this->currentTypeOffset])) {
$this->currentType = -1;
break;
}

$this->currentType = $this->types[$this->currentTypeOffset];
} while (0 === \count($this->rules[$this->currentType]));
}

public function valid(): bool
{
return isset($this->rules[$this->currentType], $this->rules[$this->currentType][$this->currentOffset]);
}
}
