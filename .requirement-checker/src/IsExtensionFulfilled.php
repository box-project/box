<?php

namespace _HumbugBoxacafcfe30294\KevinGH\RequirementChecker;

final class IsExtensionFulfilled implements \_HumbugBoxacafcfe30294\KevinGH\RequirementChecker\IsFulfilled
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
