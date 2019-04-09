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

namespace KevinGH\Box\Compactor;

use function array_keys;
use Assert\Assertion;
use function str_replace;

final class Placeholder implements Compactor
{
    private $placeholders;

    /**
     * @param scalar[] $placeholders
     */
    public function __construct(array $placeholders)
    {
        Assertion::allScalar($placeholders);

        $this->placeholders = $placeholders;
    }

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        return str_replace(
            array_keys($this->placeholders),
            $this->placeholders,
            $contents
        );
    }
}
