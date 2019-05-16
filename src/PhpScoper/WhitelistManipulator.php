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

namespace KevinGH\Box\PhpScoper;

use function array_shift;
use Assert\Assertion;
use function count;
use Humbug\PhpScoper\Whitelist;
use PhpParser\Node\Name\FullyQualified;

/**
 * @private
 */
final class WhitelistManipulator
{
    public static function mergeWhitelists(Whitelist ...$whitelists): Whitelist
    {
        Assertion::greaterThan(count($whitelists), 0, 'Expected to have at least one whitelist, none given');

        /** @var Whitelist $whitelist */
        $whitelist = clone array_shift($whitelists);

        foreach ($whitelists as $whitelistToMerge) {
            foreach ($whitelistToMerge->getRecordedWhitelistedClasses() as [$original, $alias]) {
                $whitelist->recordWhitelistedClass(
                    new FullyQualified($original),
                    new FullyQualified($alias)
                );
            }

            foreach ($whitelistToMerge->getRecordedWhitelistedFunctions() as [$original, $alias]) {
                $whitelist->recordWhitelistedFunction(
                    new FullyQualified($original),
                    new FullyQualified($alias)
                );
            }
        }

        return $whitelist;
    }
}
