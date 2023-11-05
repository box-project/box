<?php

declare (strict_types=1);
namespace HumbugBox451\KevinGH\RequirementChecker;

use function extension_loaded;
/** @internal */
final class IsExtensionConflictFulfilled implements IsFulfilled
{
    private $conflictingExtension;
    public function __construct(string $requiredExtension)
    {
        $this->conflictingExtension = $requiredExtension;
    }
    public function __invoke() : bool
    {
        return !extension_loaded($this->conflictingExtension);
    }
}
