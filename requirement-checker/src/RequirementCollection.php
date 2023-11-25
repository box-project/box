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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function count;
use function get_cfg_var;

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
     * @var list<Requirement>
     */
    private $requirements = [];

    /**
     * @var string|false
     */
    private $phpIniPath;

    /**
     * @param string|false|null $phpIniPath
     */
    public function __construct($phpIniPath = null)
    {
        $this->phpIniPath = $phpIniPath ?? get_cfg_var('cfg_file_path');
    }

    /**
     * @return Traversable<Requirement>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->requirements);
    }

    public function count(): int
    {
        return count($this->requirements);
    }

    public function add(Requirement $requirement): void
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
    public function addRequirement(IsFulfilled $checkIsFulfilled, string $testMessage, string $helpText): void
    {
        $this->add(new Requirement($checkIsFulfilled, $testMessage, $helpText));
    }

    /**
     * Returns all mandatory requirements.
     *
     * @return list<Requirement>
     */
    public function getRequirements(): array
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
        return $this->phpIniPath;
    }

    /**
     * @return bool
     */
    public function evaluateRequirements()
    {
        return array_reduce(
            $this->requirements,
            static function (bool $checkPassed, Requirement $requirement): bool {
                return $checkPassed && $requirement->isFulfilled();
            },
            true
        );
    }
}
