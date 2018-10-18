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

use KevinGH\Box\Test\FileSystemTestCase;
use PHPUnit\Framework\Error\Warning;
use function copy;
use function file_get_contents;
use function filesize;
use function KevinGH\Box\FileSystem\touch;

/**
 * @covers \KevinGH\Box\Verifier\OpenSsl
 */
class OpenSslTest extends FileSystemTestCase
{
    public const FIXTURES_DIR = __DIR__.'/../../fixtures/signed_phars';

    public function test_it_can_verify_files(): void
    {
        $path = self::FIXTURES_DIR.'/openssl.phar';

        $hash = new OpenSsl('openssl', $path);

        $hash->update(
            file_get_contents($path, false, null, 0, filesize($path) - 76)
        );

        $this->assertTrue(
            $hash->verify(
                '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A'
            )
        );
    }

    public function test_it_can_detect_incorrectly_encoded_data(): void
    {
        $file = 'openssl.phar';

        copy(self::FIXTURES_DIR.'/openssl.phar', $file);
        touch($file.'.pubkey');

        $hash = new OpenSsl('openssl', $file);

        $hash->update(
            file_get_contents($file, false, null, 0, filesize($file) - 76)
        );

        try {
            $hash->verify('it doesn\'t matter, aight');

            $this->fail('Expected exception to be thrown.');
        } catch (Warning $exception) {
            $this->assertRegExp(
                '/cannot be coerced/',
                $exception->getMessage()
            );
        }
    }
}
