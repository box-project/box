<?php

namespace _HumbugBox5b963fb2bb9ba\KevinGH\RequirementChecker;

/**
@private
*/
final class IsExtensionFulfilled implements \_HumbugBox5b963fb2bb9ba\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredExtension;
    /**
    @param
    */
    public function __construct($requiredExtension)
    {
        $this->requiredExtension = $requiredExtension;
    }
    public function __invoke()
    {
        return \extension_loaded($this->requiredExtension);
    }
}
