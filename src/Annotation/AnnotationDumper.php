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
use function array_shift;
use function array_values;
use Assert\Assertion;
use Hoa\Compiler\Llk\TreeNode;
use function implode;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * @private
 */
final class AnnotationDumper
{
    /**
     * Dumps the list of annotations from the given tree.
     *
     * @param string[] $ignored List of annotations to ignore
     *
     * @throws InvalidToken
     *
     * @return string[]
     */
    public function dump(TreeNode $node, array $ignored): array
    {
        Assertion::allString($ignored);

        $ignored = array_map('strtolower', $ignored);

        if ('#annotations' !== $node->getId()) {
            return [];
        }

        return array_values(
            array_filter(
                $this->transformNodesToString(
                    $node->getChildren(),
                    $ignored
                )
            )
        );
    }

    /**
     * @param TreeNode $nodes
     * @param string[] $ignored
     *
     * @return (string|null)[]
     */
    private function transformNodesToString(array $nodes, array $ignored): array
    {
        return array_map(
            function (TreeNode $node) use ($ignored): ?string {
                return $this->transformNodeToString($node, $ignored);
            },
            $nodes
        );
    }

    /**
     * @param string[] $ignored
     */
    private function transformNodeToString(TreeNode $node, array $ignored): ?string
    {
        switch ($node->getId()) {
            case '#annotation':
                Assertion::greaterOrEqualThan($node->getChildrenNumber(), 1);

                $children = $node->getChildren();

                /** @var TreeNode $token */
                $token = array_shift($children);
                $parameters = array_values($children);

                if ('simple_identifier' === $token->getValueToken()) {
                    Assertion::count($parameters, 0);

                    $tokenValue = $token->getValueValue();

                    return in_array(strtolower($tokenValue), $ignored, true) ? null : '@'.$tokenValue;
                }

                if ('valued_identifier' === $token->getValueToken()) {
                    $transformedChildren = $this->transformNodesToString(
                        $parameters,
                        $ignored
                    );

                    return sprintf(
                        '@%s(%s)',
                        $token->getValueValue(),
                        implode(
                            '',
                            $transformedChildren
                        )
                    );
                }

                throw InvalidToken::createForUnknownType($token);
            case 'token':
                if (in_array($node->getValueToken(), ['identifier', 'simple_identifier', 'integer', 'float', 'boolean', 'identifier_ns', 'null'], true)) {
                    return $node->getValueValue();
                }

                if ('string' === $node->getValueToken()) {
                    return sprintf('"%s"', $node->getValueValue());
                }

                if ('valued_identifier' === $node->getValueToken()) {
                    return sprintf('%s()', $node->getValueValue());
                }

                throw InvalidToken::createForUnknownType($node);
            case '#parameters':
                $transformedChildren = $this->transformNodesToString(
                    $node->getChildren(),
                    $ignored
                );

                return implode(',', $transformedChildren);
            case '#named_parameter':
            case '#pair_equal':
            case '#pair_colon':
                Assertion::same($node->getChildrenNumber(), 2);

                $name = $node->getChild(0);
                $parameter = $node->getChild(1);

                return sprintf(
                    '%s%s%s',
                    $this->transformNodeToString($name, $ignored),
                    '#pair_colon' === $node->getId() ? ':' : '=',
                    $this->transformNodeToString($parameter, $ignored)
                );

            case '#value':
                Assertion::same($node->getChildrenNumber(), 1);

                return $this->transformNodeToString($node->getChild(0), $ignored);
            case '#string':
                Assertion::lessOrEqualThan($node->getChildrenNumber(), 1);

                return 1 === $node->getChildrenNumber() ? $this->transformNodeToString($node->getChild(0), $ignored) : '""';
            case '#list':
            case '#map':
                $transformedChildren = $this->transformNodesToString(
                    $node->getChildren(),
                    $ignored
                );

                return sprintf(
                    '{%s}',
                    implode(
                        ',',
                        $transformedChildren
                    )
                );

            case '#unnamed_parameter':
            case '#reference':
                Assertion::same($node->getChildrenNumber(), 1);

                return $this->transformNodeToString($node->getChild(0), $ignored);
            case '#constant':
                Assertion::same($node->getChildrenNumber(), 2);

                return sprintf(
                    '%s::%s',
                    $this->transformNodeToString($node->getChild(0), $ignored),
                    $this->transformNodeToString($node->getChild(1), $ignored)
                );
        }

        throw InvalidToken::createForUnknownId($node);
    }
}
