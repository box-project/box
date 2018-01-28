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

use InvalidArgumentException;
use KevinGH\Box\Console\Application;
use Phar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \KevinGH\Box\Console\Command\Verify
 */
class VerifyTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../../../fixtures/verify';

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (true === (bool) ini_get('phar.readonly')) {
            $this->markTestSkipped(
                'Requires phar.readonly to be set to 0. Either update your php.ini file or run this test with '
                .'php -d phar.readonly=0.'
            );
        }

        $this->commandTester = new CommandTester((new Application())->get('verify'));
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        //TODO: check if is still necessary
        restore_error_handler();

        parent::tearDown();
    }

    /**
     * @dataProvider providePassingPharPaths
     */
    public function test_it_verifies_the_signature_of_the_given_file_using_the_phar_extension(string $pharPath): void
    {
        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        $expected = <<<'OUTPUT'
The PHAR passed verification.

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider providePassingPharPaths
     */
    public function test_it_verifies_the_signature_of_the_given_file_in_verbose_mode(string $pharPath): void
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
Verifying the PHAR "{$pharPath}"...
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
                'File "unknown" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideFailingPharPaths
     */
    public function test_a_corrupted_PHAR_fails_the_verification(string $pharPath): void
    {
        $this->commandTester->execute([
            'command' => 'verify',
            'phar' => $pharPath,
        ]);

        $this->assertSame(
            1,
            preg_match(
                '/^The PHAR failed the verification: .+$/',
                $this->commandTester->getDisplay(true)
            )
        );

        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function providePassingPharPaths()
    {
        yield 'simple PHAR' => [
            realpath(self::FIXTURES_DIR.'/simple-phar.phar'),
        ];

        yield 'PHAR signed with OpenSSL' => [
            realpath(self::FIXTURES_DIR.'/openssl-signed/php-scoper.phar'),
        ];
    }

    public function provideFailingPharPaths()
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
