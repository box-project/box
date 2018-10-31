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

namespace KevinGH\Box;

use Exception;
use Generator;
use Phar;
use PharException;
use PHPUnit\Framework\TestCase;
use function realpath;

/**
 * @covers \KevinGH\Box\Signature
 */
class SignatureTest extends TestCase
{
    public const FIXTURES_DIR = __DIR__.'/../fixtures/signed_phars';

    public function test_cannot_create_the_signature_of_non_existent_file(): void
    {
        try {
            new Signature('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_get_the_signature_of_unsigned_PHAR(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/missing.phar');

        $signature = new Signature($path);

        try {
            $signature->get();

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The phar \"$path\" is not signed.",
                $exception->getMessage()
            );
        }
    }

    public function test_returns_not_signature_if_PHAR_is_unsigned_and_signature_is_not_required(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/missing.phar');

        $signature = new Signature($path);

        $this->assertNull($signature->get(false));
    }

    public function test_it_cannot_get_the_signature_if_the_PHAR_signature_type_is_unknown(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/invalid.phar');

        $signature = new Signature($path);

        try {
            $signature->get(true);

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The signature type (ffffffff) is not recognized for the phar \"$path\".",
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider providePHARs
     */
    public function test_it_can_get_the_PHAR_signature(string $path): void
    {
        $phar = new Phar($path);

        $signature = new Signature($path);

        $expected = $phar->getSignature();
        $actual = $signature->get();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider providePHARs
     */
    public function test_it_can_verify_the_PHAR_signature(string $path): void
    {
        $sig = new Signature($path);

        $this->assertTrue($sig->verify());
    }

    public function providePHARs(): Generator
    {
        yield [self::FIXTURES_DIR.'/md5.phar'];
        yield [self::FIXTURES_DIR.'/sha1.phar'];
        yield [self::FIXTURES_DIR.'/sha256.phar'];
        yield [self::FIXTURES_DIR.'/sha512.phar'];
        yield [self::FIXTURES_DIR.'/openssl.phar'];
    }
}
