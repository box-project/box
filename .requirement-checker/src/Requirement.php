<?php

namespace _HumbugBox5af565a878e76\KevinGH\RequirementChecker;

/**
@private
@see
@license
*/
final class Requirement
{
    private $checkIsFulfilled;
    private $fulfilled;
    private $testMessage;
    private $helpText;
    /**
    @param
    @param
    @param
    */
    public function __construct($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->checkIsFulfilled = $checkIsFulfilled;
        $this->testMessage = $testMessage;
        $this->helpText = $helpText;
    }
    public function isFulfilled()
    {
        if (null === $this->fulfilled) {
            $this->fulfilled = $this->checkIsFulfilled->__invoke();
        }
        return (bool) $this->fulfilled;
    }
    /**
    @return
    */
    public function getIsFullfilledChecker()
    {
        return $this->checkIsFulfilled;
    }
    /**
    @return
    */
    public function getTestMessage()
    {
        return $this->testMessage;
    }
    /**
    @return
    */
    public function getHelpText()
    {
        return $this->helpText;
    }
}
