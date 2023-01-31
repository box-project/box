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

namespace KevinGH\RequirementChecker\AutoReview;

use Fidry\Makefile\Parser;
use Fidry\Makefile\Rule;
use function current;

final class MakefileE2ECollector
{
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
