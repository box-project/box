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

use Symfony\Component\Yaml\Yaml;
use function array_column;
use function sort;
use const SORT_STRING;

final class GAE2ECollector
{
    private const GA_FILE = __DIR__.'/../../../.github/workflows/requirement-checker.yaml';

    private const JOB_NAME = 'e2e-tests';

    /**
     * @return list<string>
     */
    public static function getExecutedE2ETests(): array
    {
        static $names;

        if (!isset($names)) {
            $names = self::findE2ENames();
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function findE2ENames(): array
    {
        $parsedYaml = Yaml::parseFile(self::GA_FILE);

        return self::findMatrixTests($parsedYaml['jobs'][self::JOB_NAME]);
    }

    /**
     * @return list<string>
     */
    private static function findMatrixTests(array $job): array
    {
        /** @var string[] $names */
        $names = array_column(
            $job['strategy']['matrix']['e2e'],
            'command',
        );

        sort($names, SORT_STRING);

        return $names;
    }
}
