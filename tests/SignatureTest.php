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

use KevinGH\Box\Exception\FileException;
use Phar;
use PharException;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SignatureTest extends TestCase
{
    public const FIXTURES_DIR = __DIR__.'/../fixtures/signature';

    private $types;

    public function getPhars()
    {
        return [
            [self::FIXTURES_DIR.'/md5.phar'],
            [self::FIXTURES_DIR.'/sha1.phar'],
            [self::FIXTURES_DIR.'/sha256.phar'],
            [self::FIXTURES_DIR.'/sha512.phar'],
            [self::FIXTURES_DIR.'/openssl.phar'],
        ];
    }

    public function testConstructNotExist(): void
    {
        try {
            new Signature('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertSame(
                'The path "/does/not/exist" does not exist or is not a file.',
                $exception->getMessage()
            );
        }
    }

    public function testCreate(): void
    {
        $this->assertInstanceOf(
            Signature::class,
            Signature::create(self::FIXTURES_DIR.'/example.phar')
        );
    }

    public function testCreateNoGbmb(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/missing.phar');
        $sig = new Signature($path);

        try {
            $sig->get();

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The phar \"$path\" is not signed.",
                $exception->getMessage()
            );
        }
    }

    public function testCreateInvalid(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/invalid.phar');
        $sig = new Signature($path);

        try {
            $sig->get(true);

            $this->fail('Expected exception to be thrown.');
        } catch (PharException $exception) {
            $this->assertSame(
                "The signature type (ffffffff) is not recognized for the phar \"$path\".",
                $exception->getMessage()
            );
        }
    }

    public function testCreateMissingNoRequire(): void
    {
        $path = realpath(self::FIXTURES_DIR.'/missing.phar');
        $sig = new Signature($path);

        $this->assertNull($sig->get(false));
    }

    /**
     * @dataProvider getPhars
     *
     * @param mixed $path
     */
    public function testGet($path): void
    {
        $phar = new Phar($path);
        $sig = new Signature($path);

        $this->assertEquals(
            $phar->getSignature(),
            $sig->get()
        );
    }

    /**
     * @dataProvider getPhars
     *
     * @param mixed $path
     */
    public function testVerify($path): void
    {
        $sig = new Signature($path);

        $this->assertTrue($sig->verify());
    }
}
