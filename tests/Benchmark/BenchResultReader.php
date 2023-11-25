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

namespace KevinGH\Box\Benchmark;

use KevinGH\Box\NotInstantiable;
use Webmozart\Assert\Assert;
use function array_map;
use function floatval;
use function simplexml_load_string;

final class BenchResultReader
{
    use NotInstantiable;

    /**
     * @return array<string, float>
     */
    public static function readMeanTimes(string $xml): array
    {
        $simpleXml = simplexml_load_string($xml);

        $result = [];

        foreach ($simpleXml->xpath('//variant') as $variant) {
            $parameterSet = (string) $variant->xpath('parameter-set/@name')[0];
            $mean = (string) $variant->xpath('stats/@mean')[0];

            $result[$parameterSet] = $mean;
        }

        Assert::allNumeric($result);

        return array_map(
            floatval(...),
            $result,
        );
    }
}
