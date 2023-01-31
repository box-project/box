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
    $prefixPath = __DIR__.'/dist/prefix';

    return file_exists($prefixPath)
        ? file_get_contents($prefixPath)
        : throw new RuntimeException(
            sprintf(
                'Could not find the dumped prefix. Make sure to run the following command:%s%s',
                PHP_EOL,
                '$ make dump_prefix',
            ),
        );
}

return [
    'prefix' => get_prefix(),
];
