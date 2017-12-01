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

use Exception;
use JShrink\Minifier;

/**
 * Compacts JSON files by re-encoding without pretty print.
 */
final class Json extends FileExtensionCompactor
{
    /**
     * @inheritdoc
     */
    public function __construct(array $extensions = ['json'])
    {
        parent::__construct($extensions);
    }

    /**
     * @inheritdoc
     */
    public function compact(string $contents): string
    {
        $decodedContents = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $contents;
        }

        return json_encode($decodedContents);
    }
}
