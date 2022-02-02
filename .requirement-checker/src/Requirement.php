<?php

namespace HumbugBox3141\KevinGH\RequirementChecker;

final class Requirement
{
    private $checkIsFulfilled;
    private $fulfilled;
    private $testMessage;
    private $helpText;
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
    public function getIsFullfilledChecker()
    {
        return $this->checkIsFulfilled;
    }
    public function getTestMessage()
    {
        return $this->testMessage;
    }
    public function getHelpText()
    {
        return $this->helpText;
    }
}
