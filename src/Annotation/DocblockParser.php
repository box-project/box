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

use Hoa\Compiler\Exception\UnrecognizedToken;
use Hoa\Compiler\Llk\Llk;
use Hoa\Compiler\Llk\TreeNode;
use Hoa\File\Read;
use function preg_replace;
use function strpos;
use function substr;
use function trim;

/**
 * @private
 */
final class DocblockParser
{
    /**
     * Parses the docblock and returns its AST.
     *
     * @throws InvalidDocblock
     */
    public function parse(string $docblock): TreeNode
    {
        $docblock = trim($docblock);

        if (0 !== strpos($docblock, '/**') || '*/' !== substr($docblock, -2)) {
            return new TreeNode('#null');
        }

        $docblock = preg_replace(
            '/(\/\*\*[\s\S]*?\@author.+?)(\<.+?\@.+?\>)([\s\S]*?\*\/)/',
            '$1$3',
            $docblock
        );

        $compiler = Llk::load(new Read(__DIR__.'/../../res/annotation-grammar.pp'));

        try {
            return $compiler->parse($docblock);
        } catch (UnrecognizedToken $exception) {
            throw InvalidDocblock::createFromHoaUnrecognizedToken($docblock, $exception);
        }
    }
}
