<?php

namespace HumbugBox3141\KevinGH\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use ReturnTypeWillChange;
use Traversable;
final class RequirementCollection implements IteratorAggregate, Countable
{
    private $requirements = array();
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->requirements);
    }
    #[ReturnTypeWillChange]
    public function count()
    {
        return \count($this->requirements);
    }
    public function add(Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }
    public function addRequirement($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->add(new Requirement($checkIsFulfilled, $testMessage, $helpText));
    }
    public function getRequirements()
    {
        return $this->requirements;
    }
    public function getPhpIniPath()
    {
        return \get_cfg_var('cfg_file_path');
    }
    public function evaluateRequirements()
    {
        return \array_reduce($this->requirements, function ($checkPassed, Requirement $requirement) {
            return $checkPassed && $requirement->isFulfilled();
        }, \true);
    }
}
