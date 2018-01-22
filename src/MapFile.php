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

/**
 * @internal
 */
final class MapFile
{
    private $map;

    /**
     * @param string[][] $map
     */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function __invoke(string $path): ?string
    {
        foreach ($this->map as $item) {
            foreach ($item as $match => $replace) {
                if ('' === $match) {
                    return $replace.$path;
                }

                if (0 === strpos($path, $match)) {
                    return preg_replace(
                        '/^'.preg_quote($match, '/').'/',
                        $replace,
                        $path
                    );
                }
            }
        }

        return null;
    }

    public function getMap(): array
    {
        return $this->map;
    }
}
