<?php declare(strict_types=1);











namespace Composer\DependencyResolver\Operation;






abstract class SolverOperation implements OperationInterface
{



protected const TYPE = '';




public function getOperationType(): string
{
return static::TYPE;
}




public function __toString()
{
return $this->show(false);
}
}
