<?php declare(strict_types=1);











namespace Composer\Console\Input;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputOption as BaseInputOption;










class InputOption extends BaseInputOption
{



private $suggestedValues;









public function __construct(string $name, $shortcut = null, ?int $mode = null, string $description = '', $default = null, $suggestedValues = [])
{
parent::__construct($name, $shortcut, $mode, $description, $default);

$this->suggestedValues = $suggestedValues;

if ([] !== $suggestedValues && !$this->acceptValue()) {
throw new LogicException('Cannot set suggested values if the option does not accept a value.');
}
}






public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
{
$values = $this->suggestedValues;
if ($values instanceof \Closure && !\is_array($values = $values($input, $suggestions))) { 
throw new LogicException(sprintf('Closure for argument "%s" must return an array. Got "%s".', $this->getName(), get_debug_type($values)));
}
if ([] !== $values) {
$suggestions->suggestValues($values);
}
}
}
