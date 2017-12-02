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

namespace KevinGH\Box\Signature;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class HashTest extends TestCase
{
    /**
     * @var Hash
     */
    private $hash;

    protected function setUp(): void
    {
        $this->hash = new Hash();
    }

    public function testInitBadAlgorithm(): void
    {
        $this->expectException(\KevinGH\Box\Exception\Exception::class);
        $this->expectExceptionMessage('Unknown hashing algorithm');

        $this->hash->init('bad algorithm', '');
    }

    public function testVerify(): void
    {
        $this->hash->init('md5', '');
        $this->hash->update('test');

        $this->assertTrue(
            $this->hash->verify(strtoupper(md5('test')))
        );
    }
}
