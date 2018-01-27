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

namespace KevinGH\Box;

use stdClass;

final class StdClassFactory
{
    private function __construct()
    {
    }

    /**
     * Creates an stdClass instance with the given attributes. For example:.
     *
     * $std = $factory->create(['foo' => 'bar', 'ping' => 'pong']);
     *
     * is equivalent to:
     *
     * $std = new \stdClass();
     * $std->foo = 'bar';
     * $std->ping = 'pong';
     *
     * @param array $attributes
     *
     * @return stdClass
     */
    public static function create(array $attributes = []): stdClass
    {
        $instance = new stdClass();

        foreach ($attributes as $attribute => $value) {
            $instance->$attribute = $value;
        }

        return $instance;
    }
}
