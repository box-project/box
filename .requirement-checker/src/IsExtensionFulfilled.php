<?php

namespace HumbugBox3160\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements IsFulfilled
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
