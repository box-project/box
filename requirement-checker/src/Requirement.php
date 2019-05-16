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

    /**
     * @param IsFulfilled $checkIsFulfilled
     * @param string      $testMessage
     * @param string      $helpText
     */
    public function __construct(
        $checkIsFulfilled,
        $testMessage,
        $helpText
    ) {
        $this->checkIsFulfilled = $checkIsFulfilled;
        $this->testMessage = $testMessage;
        $this->helpText = $helpText;
    }

    /**
     * @return bool
     */
    public function isFulfilled()
    {
        if (null === $this->fulfilled) {
            $this->fulfilled = $this->checkIsFulfilled->__invoke();
        }

        return (bool) $this->fulfilled;  // Cast to boolean, `(bool)` and `boolval()` are not available in PHP 5.3
    }

    /**
     * @return IsFulfilled
     */
    public function getIsFullfilledChecker()
    {
        return $this->checkIsFulfilled;
    }

    /**
     * @return string
     */
    public function getTestMessage()
    {
        return $this->testMessage;
    }

    /**
     * @return string
     */
    public function getHelpText()
    {
        return $this->helpText;
    }
}
