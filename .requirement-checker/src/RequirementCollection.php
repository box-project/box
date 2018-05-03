<?php

namespace _HumbugBox5aeb92ac2e46b\KevinGH\RequirementChecker;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
/**
@private
@see
@package
@license
*/
final class RequirementCollection implements \IteratorAggregate, \Countable
{
    /**
    @var
    */
    private $requirements = array();
    /**
    @return
    */
    public function getIterator()
    {
        return new \ArrayIterator($this->requirements);
    }
    public function count()
    {
        return \count($this->requirements);
    }
    /**
    @param
    */
    public function add(\_HumbugBox5aeb92ac2e46b\KevinGH\RequirementChecker\Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }
    /**
    @param
    @param
    @param
    */
    public function addRequirement($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->add(new \_HumbugBox5aeb92ac2e46b\KevinGH\RequirementChecker\Requirement($checkIsFulfilled, $testMessage, $helpText));
    }
    /**
    @return
    */
    public function getRequirements()
    {
        return $this->requirements;
    }
    /**
    @return
    */
    public function getPhpIniPath()
    {
        return \get_cfg_var('cfg_file_path');
    }
    /**
    @return
    */
    public function evaluateRequirements()
    {
        return \array_reduce(
            $this->requirements,
            /**
            @param
            @param
            @return
            */
            function ($checkPassed, \_HumbugBox5aeb92ac2e46b\KevinGH\RequirementChecker\Requirement $requirement) {
                return $checkPassed && $requirement->isFulfilled();
            },
            \true
        );
    }
}
