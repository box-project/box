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

use PHPUnit\Framework\TestCase;
use function count;

/**
 * @covers \KevinGH\Box\AutoReview\GAE2ECollector
 *
 * @internal
 */
class GAE2ECollectorTest extends TestCase
{
    public function test_it_collects_the_e2e_test_names(): void
    {
        $names = GAE2ECollector::getExecutedE2ETests();

        self::assertGreaterThan(0, count($names));

        foreach ($names as $name) {
            self::assertMatchesRegularExpression('/^e2e_[\p{L}_]+$/', $name);
        }
    }
}
