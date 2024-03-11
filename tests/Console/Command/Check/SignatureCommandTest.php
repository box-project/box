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

namespace KevinGH\Box\Console\Command\Check;

use Fidry\Console\Command\Command;
use Fidry\Console\ExitCode;
use Fidry\Console\Test\OutputAssertions;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Phar\Throwable\InvalidPhar;
use KevinGH\Box\Test\CommandTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(CheckSignatureCommand::class)]
class SignatureCommandTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../../../fixtures/phar';

    protected function getCommand(): Command
    {
        return new CheckSignatureCommand();
    }

    public function test_it_checks_the_phar_signature(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';
        $pharHash = '55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED';

        $this->commandTester->execute([
            'command' => 'check:signature',
            'phar' => $pharPath,
            'hash' => $pharHash,
        ]);

        OutputAssertions::assertSameOutput(
            '',
            ExitCode::SUCCESS,
            $this->commandTester,
        );
    }

    public function test_it_fails_if_the_phar_has_a_different_hash_than_the_expected_one_the_phar_signature(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';
        $pharHash = 'ARandomHash;CannotMatchTheRealOne';

        $this->commandTester->execute([
            'command' => 'check:signature',
            'phar' => $pharPath,
            'hash' => $pharHash,
        ]);

        OutputAssertions::assertSameOutput(
            <<<'EOF'

                 [ERROR] Found the hash "55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED".


                EOF,
            ExitCode::FAILURE,
            $this->commandTester,
            DisplayNormalizer::removeBlockLineReturn(...),
        );
    }

    public function test_it_cannot_provide_info_about_a_non_existent_phar(): void
    {
        $file = self::FIXTURES.'/foo';

        $this->expectException(InvalidPhar::class);

        $this->commandTester->execute(
            [
                'command' => 'check:signature',
                'phar' => $file,
                'hash' => '...',
            ],
        );
    }

    public function test_it_cannot_provide_info_about_an_non_phar_archive(): void
    {
        $file = self::FIXTURES.'/simple.zip';
        $expectedFilePath = Path::canonicalize($file);

        $this->commandTester->execute(
            [
                'command' => 'check:signature',
                'phar' => $file,
                'hash' => '...',
            ],
        );

        OutputAssertions::assertSameOutput(
            <<<EOF

                 [ERROR] The file "{$expectedFilePath}" is not a PHAR.


                EOF,
            ExitCode::FAILURE,
            $this->commandTester,
            DisplayNormalizer::removeBlockLineReturn(...),
        );
    }

    public function test_it_cannot_provide_info_about_an_invalid_phar(): void
    {
        $file = self::FIXTURES.'/empty-pdf.pdf';

        $this->expectException(InvalidPhar::class);

        $this->commandTester->execute(
            [
                'command' => 'check:signature',
                'phar' => $file,
                'hash' => '...',
            ],
        );
    }
}
