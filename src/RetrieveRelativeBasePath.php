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

final class RetrieveRelativeBasePath
{
    private $basePath;
    private $basePathRegex;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->basePathRegex = '/'.preg_quote($basePath.DIRECTORY_SEPARATOR, '/').'/';
    }

    public function __invoke(string $path)
    {
        return preg_replace(
            $this->basePathRegex,
            '',
            $path
        );
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
