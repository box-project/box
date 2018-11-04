<?php

namespace KevinGH\Box\Annotation;

use function array_map;
use function array_shift;
use function array_values;
use Assert\Assertion;
use Hoa\Compiler\Llk\TreeNode;
use Hoa\Visitor;
use function implode;
use function in_array;
use InvalidArgumentException;
use function sprintf;

final class AnnotationDumper
{
    /**
     * Dumps the list of annotations from the given tree.
     *
     * @return  string[]
     */
    public function dump(TreeNode $node): array
    {
        if ('#annotations' !== $node->getId()) {
            return [];
        }

        return array_map(
            function (TreeNode $node): string {
                return $this->transformDataToString($node);
            },
            $node->getChildren()
        );
    }

    private function transformDataToString(TreeNode $node): string
    {
        if ('token' === $node->getId()) {
            if (in_array($node->getValueToken(), ['identifier', 'simple_identifier', 'integer', 'float', 'boolean', 'identifier_ns'], true)) {
                return $node->getValueValue();
            }

            if ('string' === $node->getValueToken()) {
                return sprintf('"%s"', $node->getValueValue());
            }

            if ('valued_identifier' === $node->getValueToken()) {
                return sprintf('%s()', $node->getValueValue());
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Unknown token type "%s"',
                    $node->getValueToken()
                )
            );
        }

        if ('#parameters' === $node->getId()) {
            $transformedChildren = array_map(
                function (TreeNode $parameter): string {
                    return $this->transformDataToString($parameter);
                },
                $node->getChildren()
            );

            return implode(',', $transformedChildren);
        }

        if ('#named_parameter' === $node->getId() || '#pair' === $node->getId()) {
            Assertion::same($node->getChildrenNumber(), 2);

            $name = $node->getChild(0);
            $parameter = $node->getChild(1);

            return sprintf(
                '%s=%s',
                $this->transformDataToString($name),
                $this->transformDataToString($parameter)
            );
        }

        if ('#value' === $node->getId()) {
            Assertion::same($node->getChildrenNumber(), 1);

            return $this->transformDataToString($node->getChild(0));
        }

        if ('#string' === $node->getId()) {
            Assertion::lessOrEqualThan($node->getChildrenNumber(), 1);

            return $node->getChildrenNumber() === 1 ? $this->transformDataToString($node->getChild(0)) : '""';
        }

        if ('#list' === $node->getId() || '#map' === $node->getId()) {
            $transformedChildren = array_map(
                function (TreeNode $parameter): string {
                    return $this->transformDataToString($parameter);
                },
                $node->getChildren()
            );

            return sprintf(
                '{%s}',
                implode(
                    ',',
                    $transformedChildren
                )
            );
        }

        if ('#annotation' === $node->getId()) {
            Assertion::greaterOrEqualThan($node->getChildrenNumber(), 1);

            $children = $node->getChildren();

            /** @var TreeNode $token */
            $token = array_shift($children);
            $parameters = array_values($children);

            if ('simple_identifier' === $token->getValueToken()) {
                Assertion::count($parameters, 0);

                return '@'.$token->getValueValue();
            }

            if ('valued_identifier' === $token->getValueToken()) {
                $transformedChildren = array_map(
                    function (TreeNode $parameter): string {
                        return $this->transformDataToString($parameter);
                    },
                    $parameters
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
        }

        if ('#unnamed_parameter' === $node->getId()) {
            Assertion::same($node->getChildrenNumber(), 1);

            return $this->transformDataToString($node->getChild(0));
        }

        if ('#reference' === $node->getId()) {
            Assertion::same($node->getChildrenNumber(), 1);

            return $this->transformDataToString($node->getChild(0));
        }

        if ('#constant' === $node->getId()) {
            Assertion::same($node->getChildrenNumber(), 2);

            return sprintf(
                '%s::%s',
                $this->transformDataToString($node->getChild(0)),
                $this->transformDataToString($node->getChild(1))
            );
        }


        $x = '';
    }
}
