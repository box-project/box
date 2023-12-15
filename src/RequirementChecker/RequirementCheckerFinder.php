<?php

declare(strict_types=1);

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