<?php declare(strict_types=1);











namespace Composer\DependencyResolver;

use Composer\Repository\RepositorySet;







class RuleSet implements \IteratorAggregate, \Countable
{

public const TYPE_PACKAGE = 0;
public const TYPE_REQUEST = 1;
public const TYPE_LEARNED = 4;






public $ruleById = [];

const TYPES = [
self::TYPE_PACKAGE => 'PACKAGE',
self::TYPE_REQUEST => 'REQUEST',
self::TYPE_LEARNED => 'LEARNED',
];


protected $rules;


protected $nextRuleId = 0;


protected $rulesByHash = [];

public function __construct()
{
foreach ($this->getTypes() as $type) {
$this->rules[$type] = [];
}
}




public function add(Rule $rule, $type): void
{
if (!isset(self::TYPES[$type])) {
throw new \OutOfBoundsException('Unknown rule type: ' . $type);
}

$hash = $rule->getHash();


if (isset($this->rulesByHash[$hash])) {
$potentialDuplicates = $this->rulesByHash[$hash];
if (\is_array($potentialDuplicates)) {
foreach ($potentialDuplicates as $potentialDuplicate) {
if ($rule->equals($potentialDuplicate)) {
return;
}
}
} else {
if ($rule->equals($potentialDuplicates)) {
return;
}
}
}

if (!isset($this->rules[$type])) {
$this->rules[$type] = [];
}

$this->rules[$type][] = $rule;
$this->ruleById[$this->nextRuleId] = $rule;
$rule->setType($type);

$this->nextRuleId++;

if (!isset($this->rulesByHash[$hash])) {
$this->rulesByHash[$hash] = $rule;
} elseif (\is_array($this->rulesByHash[$hash])) {
$this->rulesByHash[$hash][] = $rule;
} else {
$originalRule = $this->rulesByHash[$hash];
$this->rulesByHash[$hash] = [$originalRule, $rule];
}
}

public function count(): int
{
return $this->nextRuleId;
}

public function ruleById(int $id): Rule
{
return $this->ruleById[$id];
}


public function getRules(): array
{
return $this->rules;
}

public function getIterator(): RuleSetIterator
{
return new RuleSetIterator($this->getRules());
}




public function getIteratorFor($types): RuleSetIterator
{
if (!\is_array($types)) {
$types = [$types];
}

$allRules = $this->getRules();


$rules = [];

foreach ($types as $type) {
$rules[$type] = $allRules[$type];
}

return new RuleSetIterator($rules);
}




public function getIteratorWithout($types): RuleSetIterator
{
if (!\is_array($types)) {
$types = [$types];
}

$rules = $this->getRules();

foreach ($types as $type) {
unset($rules[$type]);
}

return new RuleSetIterator($rules);
}




public function getTypes(): array
{
$types = self::TYPES;

return array_keys($types);
}

public function getPrettyString(?RepositorySet $repositorySet = null, ?Request $request = null, ?Pool $pool = null, bool $isVerbose = false): string
{
$string = "\n";
foreach ($this->rules as $type => $rules) {
$string .= str_pad(self::TYPES[$type], 8, ' ') . ": ";
foreach ($rules as $rule) {
$string .= ($repositorySet && $request && $pool ? $rule->getPrettyString($repositorySet, $request, $pool, $isVerbose) : $rule)."\n";
}
$string .= "\n\n";
}

return $string;
}

public function __toString(): string
{
return $this->getPrettyString();
}
}
