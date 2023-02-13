<?php










namespace Symfony\Component\Console\Input;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;






interface InputInterface
{





public function getFirstArgument();














public function hasParameterOption($values, bool $onlyParams = false);















public function getParameterOption($values, $default = false, bool $onlyParams = false);






public function bind(InputDefinition $definition);






public function validate();






public function getArguments();








public function getArgument(string $name);








public function setArgument(string $name, $value);






public function hasArgument(string $name);






public function getOptions();








public function getOption(string $name);








public function setOption(string $name, $value);






public function hasOption(string $name);






public function isInteractive();




public function setInteractive(bool $interactive);
}
