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

namespace {
    require __DIR__.'/../vendor/autoload.php';
}

// This file should no longer necessary as soon as we bump to PHP 8.3 and
// update dependencies.

namespace Safe {
    use Safe\Exceptions\PcreException;
    use function error_clear_last;

    if (!function_exists('Safe\preg_match')) {
        function preg_match(string $pattern, string $subject, ?iterable &$matches = null, int $flags = 0, int $offset = 0): int
        {
            error_clear_last();
            $safeResult = \preg_match($pattern, $subject, $matches, $flags, $offset);
            if (false === $safeResult) {
                throw PcreException::createFromPhpError();
            }

            return $safeResult;
        }
    }
}
