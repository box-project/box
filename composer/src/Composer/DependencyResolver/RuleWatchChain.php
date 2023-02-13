<?php declare(strict_types=1);











namespace Composer\DependencyResolver;










class RuleWatchChain extends \SplDoublyLinkedList
{





public function seek(int $offset): void
{
$this->rewind();
for ($i = 0; $i < $offset; $i++, $this->next());
}









public function remove(): void
{
$offset = $this->key();
$this->offsetUnset($offset);
$this->seek($offset);
}
}
