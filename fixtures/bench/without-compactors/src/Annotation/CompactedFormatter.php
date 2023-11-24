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

namespace BenchTest\Annotation;

use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use function array_map;
use function explode;
use function implode;

final class CompactedFormatter implements Formatter
{
    public function format(Tag $tag): string
    {
        if (!$tag instanceof Generic) {
            return trim('@'.$tag->getName());
        }

        $description = (string) $tag;

        if (!str_starts_with($description, '(')) {
            return trim('@'.$tag->getName());
        }

        $description = implode('', array_map('trim', explode("\n", (string) $tag)));

        return trim('@'.$tag->getName().$description);
    }
}
