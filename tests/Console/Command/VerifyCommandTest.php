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

namespace KevinGH\Box\Console\Command;

use Fidry\Console\Command\Command;
use Fidry\Console\ExitCode;
use InvalidArgumentException;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Phar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\OutputInterface;
use function Safe\realpath;

/**
 * @internal
 */
#[CoversClass(VerifyCommand::class)]
class VerifyCommandTest extends CommandTestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/verify';

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

    protected function getCommand(): Command
    {
        return new VerifyCommand();
    }

    #[DataProvider('passingPharPathsProvider')]
    public function test_it_verifies_the_signature_of_the_given_file_using_the_phar_extension(string $pharPath): void
    {
        $signature = (new Phar($pharPath))->getSignature();

        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        $expected = <<<OUTPUT

            🔐️  Verifying the PHAR "{$pharPath}"

            The PHAR passed verification.

            {$signature['hash_type']} signature: {$signature['hash']}

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_can_verify_a_phar_which_does_not_have_the_phar_extension(): void
    {
        $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar');

        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        $expected = <<<OUTPUT

            🔐️  Verifying the PHAR "{$pharPath}"

            The PHAR passed verification.

            SHA-1 signature: 191723EE056C62E3179FDE1B792AA03040FCEF92

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    #[DataProvider('passingPharPathsProvider')]
    public function test_it_verifies_the_signature_of_the_given_file_in_debug_mode(string $pharPath): void
    {
        $signature = (new Phar($pharPath))->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'verify',
                'phar' => $pharPath,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        $expected = <<<OUTPUT

            🔐️  Verifying the PHAR "{$pharPath}"

            The PHAR passed verification.

            {$signature['hash_type']} signature: {$signature['hash']}

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);
    }

    public function test_it_cannot_verify_an_unknown_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "unknown" does not exist.');

        $this->commandTester->execute(
            [
                'command' => 'verify',
                'phar' => 'unknown',
            ],
        );
    }

    #[DataProvider('failingPharPathsProvider')]
    public function test_a_corrupted_phar_fails_the_verification(string $pharPath): void
    {
        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        self::assertMatchesRegularExpression(
            '/The PHAR failed the verification: .+/',
            $this->commandTester->getDisplay(true),
            $this->commandTester->getDisplay(true),
        );

        self::assertSame(ExitCode::FAILURE, $this->commandTester->getStatusCode());
    }

    public static function passingPharPathsProvider(): iterable
    {
        yield 'simple PHAR' => [
            realpath(self::FIXTURES_DIR.'/simple-phar.phar'),
        ];

        yield 'PHAR signed with OpenSSL' => [
            realpath(self::FIXTURES_DIR.'/openssl-signed/php-scoper.phar'),
        ];
    }

    public static function failingPharPathsProvider(): iterable
    {
        yield 'a fake PHAR' => [
            realpath(self::FIXTURES_DIR.'/not-a-phar.phar'),
        ];

        yield 'a simple PHAR which content has been altered' => [
            realpath(self::FIXTURES_DIR.'/simple-corrupted-phar.phar'),
        ];

        yield 'an OpenSSL signed PHAR which public key has been altered' => [
            realpath(self::FIXTURES_DIR.'/openssl-signed-with-corrupted-pubkey/php-scoper.phar'),
        ];

        yield 'an OpenSSL signed PHAR without its public key' => [
            realpath(self::FIXTURES_DIR.'/openssl-signed-without-pubkey/php-scoper.phar'),
        ];
    }
}
