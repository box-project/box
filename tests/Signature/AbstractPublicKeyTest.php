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

use Exception;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class AbstractPublicKeyTest extends TestCase
{
    /**
     * @var PublicKey
     */
    private $hash;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->hash = new PublicKey();
    }

    public function testInitNotExist(): void
    {
        try {
            $this->hash->init('abc', '/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'Undefined index: code',
                $exception->getMessage()
            );
        }
    }
}
