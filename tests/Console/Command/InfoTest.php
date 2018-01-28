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

use KevinGH\Box\Console\Application;
use Phar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \KevinGH\Box\Console\Command\Info
 *
 * @runTestsInSeparateProcesses
 */
class InfoTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../../fixtures/info';

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester((new Application())->get('info'));
    }

    public function test_it_provides_the_phar_API_info(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'info',
            ]
        );

        $version = Phar::apiVersion();
        $compression = '  - '.implode("\n  - ", Phar::getSupportedCompression());
        $signatures = '  - '.implode("\n  - ", Phar::getSupportedSignatures());

        $expected = <<<OUTPUT
API Version: $version

Supported Compression:
$compression

Supported Signatures:
$signatures

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_a_phar_info(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ]
        );

        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_a_phar_info_with_the_tree_of_the_content(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--metadata' => true,
            ]
        );

        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

Contents:
a/
  bar.php [BZ2]
foo.php

Metadata:
array (
  'test' => 123,
)

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_a_phar_info_with_the_flat_tree_of_the_content(): void
    {
        $pharPath = self::FIXTURES.'/tree-phar.phar';
        $phar = new Phar($pharPath);

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
                '--list' => true,
                '--mode' => 'flat',
            ]
        );

        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

Contents:
a
a/bar.php [BZ2]
foo.php

OUTPUT;

        $this->assertSame($expected, $this->commandTester->getDisplay(true));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
