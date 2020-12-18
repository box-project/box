<?php

namespace HumbugBox3100\KevinGH\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
final class RequirementCollection implements \IteratorAggregate, \Countable
{
    private $requirements = array();
    public function getIterator()
    {
        return new \ArrayIterator($this->requirements);
    }
    public function count()
    {
        return \count($this->requirements);
    }
    public function add(\HumbugBox3100\KevinGH\RequirementChecker\Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }
    public function addRequirement($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->add(new \HumbugBox3100\KevinGH\RequirementChecker\Requirement($checkIsFulfilled, $testMessage, $helpText));
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
        return \array_reduce($this->requirements, function ($checkPassed, \HumbugBox3100\KevinGH\RequirementChecker\Requirement $requirement) {
            return $checkPassed && $requirement->isFulfilled();
        }, \true);
    }
}
