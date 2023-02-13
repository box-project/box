<?php declare(strict_types=1);











namespace Composer\DependencyResolver\Operation;






interface OperationInterface
{





public function getOperationType();







public function show(bool $lock);






public function __toString();
}
