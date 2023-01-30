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

/**
 * @private
 *
 * @see https://github.com/symfony/requirements-checker/blob/master/src/Requirement.php
 *
 * @license MIT (c) Fabien Potencier <fabien@symfony.com>
 */
final class Requirement
{
    private $checkIsFulfilled;
    private $fulfilled;
    private $testMessage;
    private $helpText;

    public function __construct(
        IsFulfilled $checkIsFulfilled,
        string $testMessage,
        string $helpText
    ) {
        $this->checkIsFulfilled = $checkIsFulfilled;
        $this->testMessage = $testMessage;
        $this->helpText = $helpText;
    }

    public function isFulfilled(): bool
    {
        if (!isset($this->fulfilled)) {
            $this->fulfilled = $this->checkIsFulfilled->__invoke();
        }

        return $this->fulfilled;
    }

    public function getIsFullfilledChecker(): IsFulfilled
    {
        return $this->checkIsFulfilled;
    }

    public function getTestMessage(): string
    {
        return $this->testMessage;
    }

    public function getHelpText(): string
    {
        return $this->helpText;
    }
}
