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

use Hoa\Compiler\Llk\TreeNode;
use function sprintf;
use UnexpectedValueException;

/**
 * @private
 */
final class InvalidToken extends UnexpectedValueException
{
    public static function createForUnknownType(TreeNode $node): self
    {
        return new self(
            sprintf(
                'Unknown token type "%s"',
                $node->getValueToken()
            )
        );
    }

    public static function createForUnknownId(TreeNode $node): self
    {
        return new self(
            sprintf(
                'Unknown token ID "%s"',
                $node->getId()
            )
        );
    }
}
