<?php

namespace _HumbugBox90eb36759167\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \_HumbugBox90eb36759167\KevinGH\RequirementChecker\IsFulfilled
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
