<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


use Humbug\PhpScoper\Whitelist;

final class WhitelistManipulator
{
    public static function mergeWhitelists(Whitelist ...$whitelists): Whitelist
    {
        $whitelistGlobalConstants = true;
        $whitelistGlobalClasses = true;
        $whitelistGlobalFunctions = true;
        $elements = [];

        foreach ($whitelists as $whitelist) {
            $whitelistGlobalConstants = $whitelistGlobalConstants && $whitelist->whitelistGlobalConstants();
            $whitelistGlobalClasses = $whitelistGlobalClasses && $whitelist->whitelistGlobalClasses();
            $whitelistGlobalFunctions = $whitelistGlobalFunctions && $whitelist->whitelistGlobalFunctions();

            $whitelistElements = $whitelist->toArray();

            foreach ($whitelistElements as $whitelistElement) {
                // Do not rely on array_merge here since it can be quite slow. Indeed array_merge copies the two arrays
                // to merge into a new one, done in a loop like here it can be quite taxing.
                $elements[] = $whitelistElement;
            }
        }

        return Whitelist::create(
            $whitelistGlobalConstants,
            $whitelistGlobalClasses,
            $whitelistGlobalFunctions,
            ...$elements
        );
    }
}