<?php

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

/**
 * @private
 */
final class IsExtensionFulfilled implements IsFulfilled
{
    private $requiredExtension;

    /**
     * @param string $requiredExtension
     */
    public function __construct($requiredExtension)
    {
        $this->requiredExtension = $requiredExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke()
    {
        return \extension_loaded($this->requiredExtension);
    }
}
