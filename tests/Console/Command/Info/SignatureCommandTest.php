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

namespace KevinGH\Box\Console\Command\Info;

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
#[CoversClass(InfoSignatureCommand::class)]
class SignatureCommandTest extends CommandTestCase
{
    private const FIXTURES = __DIR__.'/../../../../fixtures/phar';

    protected function getCommand(): Command
    {
        return new InfoSignatureCommand();
    }

    public function test_it_provides_the_phar_signature(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';

        $this->commandTester->execute([
            'command' => 'info:signature',
            'phar' => $pharPath,
        ]);

        OutputAssertions::assertSameOutput(
            <<<'EOF'
                55AE0CCD6D3A74BE41E19CD070A655A73FEAEF8342084A0801954943FBF219ED

                EOF,
            ExitCode::SUCCESS,
            $this->commandTester,
        );
    }

    public function test_it_cannot_provide_info_about_a_non_existent_phar(): void
    {
        $file = self::FIXTURES.'/foo';

        $this->expectException(InvalidPhar::class);

        $this->commandTester->execute(
            [
                'command' => 'info:signature',
                'phar' => $file,
            ],
        );
    }

    public function test_it_cannot_provide_info_about_an_non_phar_archive(): void
    {
        $file = self::FIXTURES.'/simple.zip';
        $expectedFilePath = Path::canonicalize($file);

        $this->commandTester->execute(
            [
                'command' => 'info:signature',
                'phar' => $file,
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
                'command' => 'info:signature',
                'phar' => $file,
            ],
        );
    }
}
