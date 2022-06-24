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

function get_prefix(): string
{
    $lastRelease = shell_exec('git describe --abbrev=0 --tags HEAD');

    if (!is_string($lastRelease) || '' === $lastRelease) {
        throw new RuntimeException('Invalid tag name found.');
    }

    return 'HumbugBox'.str_replace('.', '', $lastRelease);
}

return [
    'prefix' => get_prefix(),
];
