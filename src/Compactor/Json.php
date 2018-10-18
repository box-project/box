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

use const JSON_ERROR_NONE;
use function json_decode;
use function json_encode;
use function json_last_error;

/**
 * Compacts JSON files by re-encoding without pretty print.
 *
 * @private
 */
final class Json extends FileExtensionCompactor
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $extensions = ['json', 'lock'])
    {
        parent::__construct($extensions);
    }

    /**
     * {@inheritdoc}
     */
    protected function compactContent(string $contents): string
    {
        $decodedContents = json_decode($contents);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return $contents;
        }

        return json_encode($decodedContents);
    }
}
