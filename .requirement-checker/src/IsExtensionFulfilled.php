<?php

namespace _HumbugBoxaa731ba336da\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \_HumbugBoxaa731ba336da\KevinGH\RequirementChecker\IsFulfilled
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
