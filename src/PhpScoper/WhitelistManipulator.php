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

use Assert\Assertion;
use Humbug\PhpScoper\Whitelist;
use PhpParser\Node\Name\FullyQualified;
use function array_shift;
use function count;

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
            $recordedWhitelistedClasses = $whitelistToMerge->getRecordedWhitelistedClasses();

            foreach ($recordedWhitelistedClasses as [$original, $alias]) {
                $whitelist->recordWhitelistedClass(
                    new FullyQualified($original),
                    new FullyQualified($alias)
                );
            }

            $recordedWhitelistedFunctions = $whitelistToMerge->getRecordedWhitelistedFunctions();

            foreach ($recordedWhitelistedFunctions as [$original, $alias]) {
                $whitelist->recordWhitelistedFunction(
                    new FullyQualified($original),
                    new FullyQualified($alias)
                );
            }
        }

        return $whitelist;
    }
}
