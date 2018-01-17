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
use KevinGH\Box\Exception\OpenSslExceptionFactory;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;
use function KevinGH\Box\make_tmp_dir;
use function KevinGH\Box\remove_dir;

/**
 * @coversNothing
 */
class OpenSslTest extends TestCase
{
    public const FIXTURES_DIR = __DIR__.'/../../fixtures/signature';

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $tmp;

    /**
     * @var OpenSsl
     */
    private $hash;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmp = make_tmp_dir('box', __CLASS__);

        chdir($this->tmp);

        $this->hash = new OpenSsl();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        unset($this->box, $this->phar);

        chdir($this->cwd);

        remove_dir($this->tmp);

        parent::tearDown();
    }

    public function testVerify(): void
    {
        $path = self::FIXTURES_DIR.'/openssl.phar';

        $this->hash->init('openssl', $path);
        $this->hash->update(
            file_get_contents($path, false, null, 0, filesize($path) - 76)
        );

        $this->assertTrue(
            $this->hash->verify(
                '54AF1D4E5459D3A77B692E46FDB9C965D1C7579BD1F2AD2BECF4973677575444FE21E104B7655BA3D088090C28DF63D14876B277C423C8BFBCDB9E3E63F9D61A'
            )
        );
    }

    public function testVerifyErrorHandlingBug(): void
    {
        Warning::$enabled = false;

        mkdir($dir = 'foo');
        $path = "$dir/openssl.phar";

        copy(self::FIXTURES_DIR.'/openssl.phar', $path);
        touch("$path.pubkey");

        $this->hash->init('openssl', $path);
        $this->hash->update(
            file_get_contents($path, false, null, 0, filesize($path) - 76)
        );

        try {
            $this->hash->verify('it dont matter, aight');

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertRegExp(
                '/cannot be coerced/',
                $exception->getMessage()
            );
        }

        Warning::$enabled = true;
    }
}
