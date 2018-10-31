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

use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function filesize;

/**
 * @covers \KevinGH\Box\Verifier\PublicKeyDelegate
 */
class PublicKeyDelegateTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/signed_phars';

    public function testFunctional(): void
    {
        $path = self::FIXTURES_DIR.'/openssl.phar';

        $hash = new PublicKeyDelegate('openssl', $path);

        $hash->update(
            file_get_contents($path, false, null, 0, filesize($path) - 76)
        );

        $this->assertTrue(
            $hash->verify(
                '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A'
            )
        );
    }
}
