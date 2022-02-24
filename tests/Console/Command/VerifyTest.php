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

use Generator;
use InvalidArgumentException;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Phar;
use function realpath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \KevinGH\Box\Console\Command\Verify
 */
class VerifyTest extends CommandTestCase
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
        return new Verify();
    }

    /**
     * @dataProvider providePassingPharPaths
     */
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

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
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

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider providePassingPharPaths
     */
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
            ]
        );

        $expected = <<<OUTPUT

            🔐️  Verifying the PHAR "{$pharPath}"

            The PHAR passed verification.

            {$signature['hash_type']} signature: {$signature['hash']}

            OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_cannot_verify_an_unknown_file(): void
    {
        try {
            $this->commandTester->execute(
                [
                    'command' => 'verify',
                    'phar' => 'unknown',
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The file "unknown" does not exist.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideFailingPharPaths
     */
    public function test_a_corrupted_phar_fails_the_verification(string $pharPath): void
    {
        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        $this->assertMatchesRegularExpression(
            '/The PHAR failed the verification: .+/',
            $this->commandTester->getDisplay(true),
            $this->commandTester->getDisplay(true)
        );

        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function providePassingPharPaths(): Generator
    {
        yield 'simple PHAR' => [
            realpath(self::FIXTURES_DIR.'/simple-phar.phar'),
        ];

        yield 'PHAR signed with OpenSSL' => [
            realpath(self::FIXTURES_DIR.'/openssl-signed/php-scoper.phar'),
        ];
    }

    public function provideFailingPharPaths(): Generator
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
