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

namespace KevinGH\Box\Verifier;

use InvalidArgumentException;
use KevinGH\Box\Test\FileSystemTestCase;
use function KevinGH\Box\FileSystem\chmod;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\touch;

/**
 * @covers \KevinGH\Box\Verifier\PublicKey
 */
class PublicKeyTest extends FileSystemTestCase
{
    public function test_it_cannot_be_initialised_with_a_non_existent_file(): void
    {
        try {
            new DummyPublicKey('data', '/nowhere/foo');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/nowhere/foo.pubkey" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_be_initialised_with_a_non_readablefile(): void
    {
        $file = 'foo';
        $key = $file.'.pubkey';

        touch($key);
        chmod($key, 0355);

        try {
            new DummyPublicKey('data', $file);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Path "foo.pubkey" was expected to be readable.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_retrieves_the_content_of_the_file_public_key_on_init(): void
    {
        $file = 'foo';
        $key = $file.'.pubkey';

        dump_file($key, 'key file contents');

        $hash = new DummyPublicKey('data', $file);

        $expected = 'key file contents';
        $actual = $hash->getExposedKey();

        $this->assertSame($expected, $actual);
    }
}
