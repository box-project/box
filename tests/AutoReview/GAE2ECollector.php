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

namespace KevinGH\Box\AutoReview;

use Symfony\Component\Yaml\Yaml;
use function sort;
use function str_starts_with;
use const SORT_STRING;

final class GAE2ECollector
{
    private const GA_FILE = __DIR__.'/../../.github/workflows/e2e-tests.yaml';

    private const JOB_NAMES = 'e2e-tests';
    private const DOCKER_JOB_NAME = 'e2e-tests-docker';

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

        $names = [
            ...self::findMatrixTests($parsedYaml['jobs'][self::JOB_NAMES]),
        ];

        foreach (self::findMatrixTests($parsedYaml['jobs'][self::DOCKER_JOB_NAME]) as $name) {
            $names[] = str_starts_with($name, '_')
                ? mb_substr($name, 1)
                : $name;
        }

        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function findMatrixTests(array $job): array
    {
        /** @var string[] $names */
        $names = $job['strategy']['matrix']['e2e'];

        sort($names, SORT_STRING);

        return $names;
    }
}
