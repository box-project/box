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

namespace KevinGH\Box\RequirementChecker;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Webmozart\Assert\Assert;

final class RequirementCheckerFinder
{
    private const REQUIREMENT_CHECKER_PATH = __DIR__.'/../../res/requirement-checker';

    /**
     * @return iterable<array{string, string}>
     */
    public function find(): iterable
    {
        Assert::directory(
            self::REQUIREMENT_CHECKER_PATH,
            'Expected the requirement checker to have been dumped',
        );

        /** @var SplFileInfo[] $requirementCheckerFiles */
        $requirementCheckerFiles = Finder::create()
            ->files()
            ->in(self::REQUIREMENT_CHECKER_PATH);

        foreach ($requirementCheckerFiles as $file) {
            yield [
                $file->getRelativePathname(),
                $file->getContents(),
            ];
        }
    }
}
