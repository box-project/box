<?php

namespace _HumbugBox87c495005ea2\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \_HumbugBox87c495005ea2\KevinGH\RequirementChecker\IsFulfilled
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
