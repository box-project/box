<?php

namespace HumbugBox383\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \HumbugBox383\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredExtension;
    public function __construct($requiredExtension)
    {
        $this->requiredExtension = $requiredExtension;
    }
    public function __invoke()
    {
        return \extension_loaded($this->requiredExtension);
    }
}
