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
 * Compacts Javascript files using JShrink.
 *
 * @author Robert Hafner <tedivm@tedivm.com>
 */
final class Javascript extends FileExtensionCompactor
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $extensions = ['js'])
    {
        parent::__construct($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function compact(string $contents): string
    {
        try {
            return Minifier::minify($contents);
        } catch (Exception $e) {
            // Returns unchanged content

            return $contents;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $file): bool
    {
        if (!parent::supports($file)) {
            return false;
        }

        return '.min.js' !== substr($file, -7);
    }
}
