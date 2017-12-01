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

/**
 * An abstract compactor class that handles matching supported file by their types.
 */
abstract class FileExtensionCompactor implements Compactor
{
    private $extensions;

    /**
     * @param string[] $extensions The list of supported file extensions.
     */
    public function __construct(array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @inheritdoc
     */
    public function supports(string $file): bool
    {
        return in_array(
            pathinfo(
                $file,
                PATHINFO_EXTENSION
            ),
            $this->extensions,
            true
        );
    }
}
