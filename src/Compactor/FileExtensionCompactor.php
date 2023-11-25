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

use Webmozart\Assert\Assert;
use function in_array;
use function pathinfo;
use const PATHINFO_EXTENSION;

/**
 * An abstract compactor class that handles matching supported file by their types.
 *
 * @private
 */
abstract class FileExtensionCompactor extends BaseCompactor
{
    /**
     * @var string[]
     */
    private readonly array $extensions;

    /**
     * @param string[] $extensions the list of supported file extensions
     */
    public function __construct(array $extensions)
    {
        Assert::allString($extensions);

        $this->extensions = $extensions;
    }

    protected function supports(string $file): bool
    {
        return in_array(
            pathinfo(
                $file,
                PATHINFO_EXTENSION,
            ),
            $this->extensions,
            true,
        );
    }
}
