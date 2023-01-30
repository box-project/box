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

namespace KevinGH\Box\Configuration;

use Webmozart\Assert\Assert;
use function array_keys;
use function trim;

/**
 * @private
 */
final class ConfigurationLogger
{
    /**
     * @var array<string, true>
     */
    private array $recommendations = [];

    /**
     * @var array<string, true>
     */
    private array $warnings = [];

    public function addRecommendation(string $message): void
    {
        $message = trim($message);

        Assert::false('' === $message, 'Expected to have a message but a blank string was given instead.');

        $this->recommendations[$message] = true;
    }

    /**
     * @return list<string>
     */
    public function getRecommendations(): array
    {
        return array_keys($this->recommendations);
    }

    public function addWarning(string $message): void
    {
        $message = trim($message);

        Assert::false('' === $message, 'Expected to have a message but a blank string was given instead.');

        $this->warnings[$message] = true;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return array_keys($this->warnings);
    }
}
