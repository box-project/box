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

namespace KevinGH\Box\Annotation;

use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use function array_map;
use function explode;
use function implode;
use function sprintf;

final class CompactedFormatter implements Formatter
{
    public function format(Tag $tag): string
    {
        if ($tag instanceof InvalidTag) {
            return self::formatInvalidTag($tag);
        }

        if ($tag instanceof Generic) {
            return self::formatGenericTag($tag);
        }

        return trim('@'.$tag->getName());
    }

    private static function formatInvalidTag(InvalidTag $tag): string
    {
        return sprintf(
            '@%s %s',
            $tag->getName(),
            $tag,
        );
    }

    private static function formatGenericTag(Generic $tag): string
    {
        $description = (string) $tag;

        if (!str_starts_with($description, '(')) {
            return trim('@'.$tag->getName());
        }

        $description = implode('', array_map('trim', explode("\n", (string) $tag)));

        return trim('@'.$tag->getName().$description);
    }
}
