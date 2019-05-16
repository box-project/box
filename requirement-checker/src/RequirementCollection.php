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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @private
 *
 * @see https://github.com/symfony/requirements-checker/blob/master/src/RequirementCollection.php
 *
 * @license MIT (c) Fabien Potencier <fabien@symfony.com>
 */
final class RequirementCollection implements IteratorAggregate, Countable
{
    /**
     * @var Requirement[]
     */
    private $requirements = array();

    /**
     * {@inheritdoc}
     *
     * @return Requirement[]|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->requirements);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return \count($this->requirements);
    }

    /**
     * @param Requirement $requirement
     */
    public function add(Requirement $requirement)
    {
        $this->requirements[] = $requirement;
    }

    /**
     * Adds a mandatory requirement evaluated lazily.
     *
     * @param IsFulfilled $checkIsFulfilled whether the requirement is fulfilled; This string is will be evaluated with `eval()` because
     *                                      PHP does not support the serialization or the export of closures
     * @param string      $testMessage      The message for testing the requirement
     * @param string      $helpText         The help text (when null, it will be inferred from $helpHtml, i.e. stripped from HTML tags)
     */
    public function addRequirement($checkIsFulfilled, $testMessage, $helpText)
    {
        $this->add(new Requirement($checkIsFulfilled, $testMessage, $helpText));
    }

    /**
     * Returns all mandatory requirements.
     *
     * @return Requirement[]
     */
    public function getRequirements()
    {
        return $this->requirements;
    }

    /**
     * Returns the PHP configuration file (php.ini) path.
     *
     * @return false|string php.ini file path
     */
    public function getPhpIniPath()
    {
        return get_cfg_var('cfg_file_path');
    }

    /**
     * @return bool
     */
    public function evaluateRequirements()
    {
        return array_reduce(
            $this->requirements,
            /**
             * @param bool        $checkPassed
             * @param Requirement $requirement
             *
             * @return bool
             */
            function ($checkPassed, Requirement $requirement) {
                return $checkPassed && $requirement->isFulfilled();
            },
            true
        );
    }
}
