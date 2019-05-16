<?php

namespace _HumbugBox90eb36759167\KevinGH\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
/**
@symfony
*/
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
    public function add(\_HumbugBox90eb36759167\KevinGH\RequirementChecker\Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }
    public function addRequirement($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->add(new \_HumbugBox90eb36759167\KevinGH\RequirementChecker\Requirement($checkIsFulfilled, $testMessage, $helpText));
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
        return \array_reduce($this->requirements, static function ($checkPassed, \_HumbugBox90eb36759167\KevinGH\RequirementChecker\Requirement $requirement) {
            return $checkPassed && $requirement->isFulfilled();
        }, \true);
    }
}
