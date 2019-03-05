<?php

namespace _HumbugBoxbb220723f65b\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \_HumbugBoxbb220723f65b\KevinGH\RequirementChecker\IsFulfilled
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
