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

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PHPUnit\Framework\Assert;
use function Safe\file_get_contents;
use function Safe\json_decode;

final readonly class AppRequirementFactoryBench
{
    private const FIXTURES = __DIR__.'/../../fixtures/bench/requirement-checker';

    public function setUp(): void
    {
        self::assertVendorsAreInstalled();
    }

    #[Iterations(1000)]
    #[BeforeMethods('setUp')]
    public function bench(): void
    {
        AppRequirementsFactory::create(
            new ComposerJson(
                '',
                json_decode(
                    file_get_contents(self::FIXTURES.'/composer.json'),
                    true,
                ),
            ),
            new ComposerLock(
                '',
                json_decode(
                    file_get_contents(self::FIXTURES.'/composer.lock'),
                    true,
                ),
            ),
            CompressionAlgorithm::BZ2,
        );
    }

    private static function assertVendorsAreInstalled(): void
    {
        Assert::assertDirectoryExists(self::FIXTURES.'/vendor');
    }
}
