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

use function KevinGH\Box\FileSystem\make_path_relative;
use function preg_quote;
use function preg_replace;
use function strpos;

/**
 * @internal
 *
 * @private
 */
final class MapFile
{
    private $basePath;
    private $map;

    /**
     * @param string[][] $map
     */
    public function __construct(string $basePath, array $map)
    {
        $this->basePath = $basePath;
        $this->map = $map;
    }

    public function __invoke(string $path): ?string
    {
        $relativePath = make_path_relative($path, $this->basePath);

        foreach ($this->map as $item) {
            foreach ($item as $match => $replace) {
                if ('' === $match) {
                    return $replace.'/'.$relativePath;
                }

                if (0 === strpos($relativePath, $match)) {
                    return preg_replace(
                        '/^'.preg_quote($match, '/').'/',
                        $replace,
                        $relativePath
                    );
                }
            }
        }

        return $relativePath;
    }

    public function getMap(): array
    {
        return $this->map;
    }
}
