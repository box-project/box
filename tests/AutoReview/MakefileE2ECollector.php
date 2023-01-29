<?php

declare(strict_types=1);

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KevinGH\Box\AutoReview;

use Fidry\Makefile\Parser;
use Fidry\Makefile\Rule;
use Humbug\PhpScoper\NotInstantiable;
use Symfony\Component\Yaml\Yaml;
use function sort;
use function str_starts_with;
use function substr;
use const SORT_STRING;

final class MakefileE2ECollector
{
    use NotInstantiable;

    public static function getE2ERule(): Rule
    {
        static $e2eRule;

        if (!isset($e2eRule)) {
            $e2eRule = self::findE2ERule();
        }

        return $e2eRule;
    }

    private static function findE2ERule(): Rule
    {
        return current(
            array_filter(
                Parser::parse(
                    file_get_contents(MakefileTest::MAKEFILE_PATH),
                ),
                static fn (Rule $rule) => 'test_e2e' === $rule->getTarget() && !$rule->isComment() && !$rule->isPhony(),
            ),
        );
    }
}
