<?php

namespace _HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker;

/**
@private
*/
final class IsExtensionFulfilled implements \_HumbugBoxc5a6d13bc633\KevinGH\RequirementChecker\IsFulfilled
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
