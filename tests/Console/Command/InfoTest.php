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
use KevinGH\Box\Console\DisplayNormalizer;
use Phar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use function preg_replace;
use function realpath;

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

    public function test_it_provides_info_about_the_phar_API(): void
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

 // Get a PHAR details by giving its path as an argument.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_info_about_a_phar(): void
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

Metadata: None

Contents: 1 file (6.61KB)

 // Use the --list|-l option to list the content of the PHAR.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_info_about_a_phar_without_extension(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar';
        $phar = new Phar($pharPath.'.phar');

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

Metadata: None

Contents: 1 file (6.61KB)

 // Use the --list|-l option to list the content of the PHAR.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_cannot_provide_info_about_an_invalid_phar_without_extension(): void
    {
        $file = self::FIXTURES.'/foo';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $file,
            ]
        );

        $expectedPath = realpath($file);

        $expected = <<<OUTPUT

 [ERROR] Could not read the file "$expectedPath".


OUTPUT;

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));
        $actual = preg_replace('/file[\ \n]+"/', 'file "', $actual);

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_info_about_a_targz_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.tar.gz';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ]
        );

        $expected = <<<'OUTPUT'

API Version: No information found

Archive Compression: GZ

Metadata: None

Contents: 1 file (2.56KB)

 // Use the --list|-l option to list the content of the PHAR.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_info_about_a_tarbz2_phar(): void
    {
        $pharPath = self::FIXTURES.'/simple-phar.tar.bz2';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ]
        );

        $expected = <<<'OUTPUT'

API Version: No information found

Archive Compression: BZ2

Metadata: None

Contents: 1 file (2.71KB)

 // Use the --list|-l option to list the content of the PHAR.


OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function test_it_provides_a_zip_phar_info(): void
    {
        $pharPath = self::FIXTURES.'/new-simple-phar.zip';

        $this->commandTester->execute(
            [
                'command' => 'info',
                'phar' => $pharPath,
            ]
        );

        $canonicalizedPath = realpath($pharPath);

        $expected = <<<OUTPUT

 [ERROR] Could not read the file "$canonicalizedPath".


OUTPUT;

        $actual = DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true));
        $actual = preg_replace('/file[\ \n]+"/', 'file "', $actual);

        $this->assertSame($expected, $actual);
        $this->assertSame(1, $this->commandTester->getStatusCode());
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

Archive Compression:
  - BZ2 (50.00%)
  - None (50.00%)

Signature: {$signature['hash_type']}
Signature Hash: {$signature['hash']}

Metadata:
array (
  'test' => 123,
)

Contents: 2 files (6.71KB)
a/
  bar.php [BZ2]
foo.php [NONE]

OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
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

Archive Compression:
  - BZ2 (50.00%)
  - None (50.00%)

Signature: {$signature['hash_type']}
Signature Hash: {$signature['hash']}

Metadata:
array (
  'test' => 123,
)

Contents: 2 files (6.71KB)
a/bar.php [BZ2]
foo.php [NONE]

OUTPUT;

        $this->assertSame($expected, DisplayNormalizer::removeTrailingSpaces($this->commandTester->getDisplay(true)));
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
