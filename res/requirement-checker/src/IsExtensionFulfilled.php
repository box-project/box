<?php

declare (strict_types=1);
namespace HumbugBox438\KevinGH\RequirementChecker;

use function extension_loaded;
final class IsExtensionFulfilled implements IsFulfilled
{
    private $requiredExtension;
    public function __construct(string $requiredExtension)
    {
        $this->requiredExtension = $requiredExtension;
    }
    public function __invoke() : bool
    {
        return extension_loaded($this->requiredExtension);
    }
}
