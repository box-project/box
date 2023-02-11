<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\RequirementChecker;

use function extension_loaded;

/**
 * @private
 */
final class IsExtensionConflictFulfilled implements IsFulfilled
{
    private $conflictingExtension;

    public function __construct(string $requiredExtension)
    {
        $this->conflictingExtension = $requiredExtension;
    }

    public function __invoke(): bool
    {
        return !extension_loaded($this->conflictingExtension);
    }
}
