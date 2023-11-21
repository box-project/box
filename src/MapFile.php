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

use Symfony\Component\Filesystem\Path;
use function preg_quote;
use function preg_replace;

/**
 * @internal
 *
 * @private
 */
final readonly class MapFile
{
    /**
     * @param string[][] $map
     */
    public function __construct(
        // Cannot have readonly properties: requires to be serializable
        private string $basePath,
        private array $map,
    ) {
    }

    public function __invoke(string $path): ?string
    {
        $relativePath = Path::makeRelative($path, $this->basePath);

        foreach ($this->map as $item) {
            foreach ($item as $match => $replace) {
                if ('' === $match) {
                    return $replace.'/'.$relativePath;
                }

                if (str_starts_with($relativePath, $match)) {
                    return preg_replace(
                        '/^'.preg_quote($match, '/').'/',
                        $replace,
                        $relativePath,
                    );
                }
            }
        }

        return $relativePath;
    }

    /**
     * @return string[][] $map
     */
    public function getMap(): array
    {
        return $this->map;
    }
}
