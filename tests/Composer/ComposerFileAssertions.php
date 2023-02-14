<?php

declare(strict_types=1);

namespace KevinGH\Box\Composer;


use PHPUnit\Framework\Assert;

final class ComposerFileAssertions
{
    private function __construct()
    {
    }

    public static function assertStateIs(
        ComposerFile $actual,
        ?string       $expectedPath,
        string       $expectedContents,
        array $expectedDecodedContents
    ): void
    {
        Assert::assertSame($expectedPath, $actual->getPath());
        Assert::assertSame($expectedContents, $actual->getContents());
        Assert::assertSame($expectedDecodedContents, $actual->getDecodedContents());
    }
}
