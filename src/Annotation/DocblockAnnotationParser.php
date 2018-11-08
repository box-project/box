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

/**
 * @private
 */
final class DocblockAnnotationParser
{
    private $docblockParser;
    private $annotationDumper;
    private $ignored;

    /**
     * @param string[] $ignored
     */
    public function __construct(DocblockParser $docblockParser, AnnotationDumper $annotationDumper, array $ignored)
    {
        $this->docblockParser = $docblockParser;
        $this->annotationDumper = $annotationDumper;
        $this->ignored = $ignored;
    }

    /**
     * @throws InvalidDocblock
     * @throws InvalidToken
     *
     * @return string[] Parsed compacted annotations parsed from the docblock
     */
    public function parse(string $docblock): array
    {
        return $this->annotationDumper->dump(
            $this->docblockParser->parse($docblock),
            $this->ignored
        );
    }
}
