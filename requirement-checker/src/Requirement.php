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
 * @see \Symfony\Requirements\Requirement
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
     * @param string $checkIsFulfilled Callable as a string (it will be evaluated with `eval()` returning a `bool` value telling whether the
     *                                 requirement is fulfilled or not. The condition is evaluated lazily.
     * @param string $testMessage      The message for testing the requirement
     * @param string $helpText         The help text (when null, it will be inferred from $helpHtml, i.e. stripped from HTML tags)
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

    public function isFulfilled()
    {
        if (null === $this->fulfilled) {
            $this->fulfilled = eval($this->checkIsFulfilled);
        }

        return (bool) $this->fulfilled;  // Cast to boolean, `(bool)` and `boolval()` are not available in PHP 5.3
    }

    /**
     * @return string
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
