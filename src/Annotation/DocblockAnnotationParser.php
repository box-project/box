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

use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_values;

/**
 * @private
 */
final class DocblockAnnotationParser
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $ignoredAnnotationsAsKeys;

    /**
     * @param string[] $ignoredAnnotations
     */
    public function __construct(
        private readonly DocBlockFactoryInterface $factory,
        private readonly Formatter $tagsFormatter,
        array $ignoredAnnotations,
    ) {
        $this->ignoredAnnotationsAsKeys = array_flip($ignoredAnnotations);
    }

    /**
     * @return string[] Parsed compacted annotations parsed from the docblock
     */
    public function parse(string $docblock): array
    {
        $doc = $this->createDocBlock($docblock);

        $tags = self::extractTags($doc, $this->ignoredAnnotationsAsKeys);

        return array_map(
            fn (Tag $tag) => $tag->render($this->tagsFormatter),
            $tags,
        );
    }

    private function createDocBlock(string $docblock): DocBlock
    {
        try {
            return $this->factory->create($docblock);
        } catch (InvalidArgumentException $invalidDocBlock) {
            throw new MalformedTagException(
                'The annotations could not be parsed.',
                0,
                $invalidDocBlock,
            );
        }
    }

    /**
     * @param array<string, mixed> $ignoredAnnotations
     *
     * @return list<string>
     */
    private static function extractTags(DocBlock $docBlock, array $ignoredAnnotations): array
    {
        return array_values(
            array_filter(
                $docBlock->getTags(),
                static fn (Tag $tag) => !array_key_exists(
                    mb_strtolower($tag->getName()),
                    $ignoredAnnotations,
                ),
            ),
        );
    }
}
