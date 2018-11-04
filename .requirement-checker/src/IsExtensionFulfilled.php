<?php

namespace _HumbugBox9d880d18ae09\KevinGH\RequirementChecker;

/**
@private
*/
final class IsExtensionFulfilled implements \_HumbugBox9d880d18ae09\KevinGH\RequirementChecker\IsFulfilled
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
