<?php










namespace Symfony\Component\Console\Helper;




class TableRows implements \IteratorAggregate
{
private $generator;

public function __construct(\Closure $generator)
{
$this->generator = $generator;
}

public function getIterator(): \Traversable
{
return ($this->generator)();
}
}
