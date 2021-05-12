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

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use function strtolower;

/**
 * @private
 */
final class DocblockAnnotationParser
{
    private $factory;
    private $tagsFormatter;
    private $ignored;

    /**
     * @param string[] $ignored
     */
    public function __construct(DocBlockFactoryInterface $factory, Formatter $tagsFormatter, array $ignored)
    {
        $this->factory = $factory;
        $this->ignored = $ignored;
        $this->tagsFormatter = $tagsFormatter;
    }

    /**
     * @return string[] Parsed compacted annotations parsed from the docblock
     */
    public function parse(string $docblock): array
    {
        try {
            $doc = $this->factory->create($docblock);
        } catch (InvalidArgumentException $e) {
            throw new MalformedTagException('The annotations could not be parsed.', 0, $e);
        }

        $tags = array_values(
            array_filter(
                $doc->getTags(),
                function (Tag $tag) {
                    return !in_array(strtolower($tag->getName()), $this->ignored, true);
                }
            )
        );

        return array_map(
            function (Tag $tag) {
                return $tag->render($this->tagsFormatter);
            },
            $tags
        );
    }
}
