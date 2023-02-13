<?php declare(strict_types=1);











namespace Composer\Console\Input;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument as BaseInputArgument;










class InputArgument extends BaseInputArgument
{



private $suggestedValues;










public function __construct(string $name, ?int $mode = null, string $description = '', $default = null, $suggestedValues = [])
{
parent::__construct($name, $mode, $description, $default);

$this->suggestedValues = $suggestedValues;
}






public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
{
$values = $this->suggestedValues;
if ($values instanceof \Closure && !\is_array($values = $values($input, $suggestions))) { 
throw new LogicException(sprintf('Closure for option "%s" must return an array. Got "%s".', $this->getName(), get_debug_type($values)));
}
if ([] !== $values) {
$suggestions->suggestValues($values);
}
}
}
