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

use Amp\MultiReasonException;
use Exception;
use InvalidArgumentException;
use KevinGH\Box\Compactor\FakeCompactor;
use KevinGH\Box\Test\FileSystemTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function KevinGH\Box\FileSystem\canonicalize;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use function array_filter;
use function current;
use function in_array;
use function realpath;

/**
 * @covers \KevinGH\Box\Box
 */
class BoxTest extends FileSystemTestCase
{
    /**
     * @var Box
     */
    private $box;

    /**
     * @var Phar
     */
    private $phar;

    /**
     * @var Compactor|ObjectProphecy
     */
    private $compactorProphecy;

    /**
     * @var Compactor
     */
    private $compactor;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->box = Box::create('test.phar');
        $this->phar = $this->box->getPhar();

        $this->compactorProphecy = $this->prophesize(Compactor::class);
        $this->compactor = $this->compactorProphecy->reveal();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (false !== $pharPath = $this->box->getPhar()->getRealPath()) {
            Phar::unlinkArchive($pharPath);
        }

        unset($this->box);
    }

    public function test_it_can_add_a_file_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        file_put_contents($file, $contents);

        $this->box->addFile($file);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_file_with_absolute_path_to_the_phar(): void
    {
        $relativePath = 'path-to/foo';
        $file = canonicalize($this->tmp.DIRECTORY_SEPARATOR.$relativePath);
        $contents = 'test';

        dump_file($file, $contents);

        $this->box->addFile($file);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$relativePath;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_file_with_a_local_path_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';
        $localPath = 'local/path/foo';

        file_put_contents($file, $contents);

        $fileMapper = new MapFile([
            [$file => $localPath],
        ]);

        $this->box->registerFileMapping($this->tmp, $fileMapper);

        $this->box->addFile($file);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_binary_file_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        file_put_contents($file, $contents);

        $this->box->registerCompactors([new FakeCompactor()]);

        $this->box->addFile($file, null, true);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_binary_file_with_a_local_path_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';
        $localPath = 'local/path/foo';

        file_put_contents($file, $contents);

        $fileMapper = new MapFile([
            [$file => $localPath],
        ]);

        $this->box->registerFileMapping($this->tmp, $fileMapper);

        $this->box->addFile($file, null, true);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_compacts_the_file_content_and_replace_placeholders_before_adding_it_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'original contents @foo_placeholder@';
        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value',
        ];

        file_put_contents($file, $contents);

        $firstCompactorProphecy = $this->prophesize(Compactor::class);
        $firstCompactorProphecy
            ->compact($file, 'original contents foo_value')
            ->willReturn($firstCompactorOutput = 'first compactor contents')
        ;

        $secondCompactorProphecy = $this->prophesize(Compactor::class);
        $secondCompactorProphecy
            ->compact($file, $firstCompactorOutput)
            ->willReturn($secondCompactorOutput = 'second compactor contents')
        ;

        $this->box->registerCompactors([
            $firstCompactorProphecy->reveal(),
            $secondCompactorProphecy->reveal(),
        ]);

        $this->box->registerPlaceholders($placeholderMapping);
        $this->box->addFile($file);

        $expectedContents = $secondCompactorOutput;
        $expectedPharPath = 'phar://test.phar/'.$file;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);

        $firstCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $secondCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_maps_the_file_before_adding_it_to_the_phar(): void
    {
        $map = new MapFile([
            ['acme' => 'src/Foo'],
            ['' => 'lib'],
        ]);

        $files = [
            'acme/foo' => 'src/Foo/foo',
            'acme/bar' => 'src/Foo/bar',
            'f1' => 'lib/f1',
            'f2' => 'lib/f2',
        ];

        $this->box->registerFileMapping($this->tmp, $map);

        foreach ($files as $file => $expectedLocal) {
            dump_file($file);

            $local = $this->box->addFile($file);

            $this->assertSame($expectedLocal, $local);

            $this->assertFileExists(
                (string) $this->box->getPhar()[$local],
                'Expected to find the file "%s" in the PHAR.'
            );

            $pathInPhar = str_replace(
                'phar://'.$this->box->getPhar()->getPath().'/',
                '',
                (string) $this->box->getPhar()[$local]
            );

            $this->assertSame($expectedLocal, $pathInPhar);
        }
    }

    public function test_it_cannot_add_an_non_existent_file_to_the_phar(): void
    {
        try {
            $this->box->addFile('/nowhere/foo');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/nowhere/foo" was expected to exist.',
                $exception->getMessage()
            );
            $this->assertSame(102, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_it_cannot_add_an_unreadable_file(): void
    {
        $file = 'foo';
        $contents = 'test';

        file_put_contents($file, $contents);
        chmod($file, 0355);

        try {
            $this->box->addFile($file);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                "Path \"${file}\" was expected to be readable.",
                $exception->getMessage()
            );
            $this->assertSame(103, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_it_compacts_the_contents_before_adding_it_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'original contents @foo_placeholder@';
        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value',
        ];

        file_put_contents($file, $contents);

        $firstCompactorProphecy = $this->prophesize(Compactor::class);
        $firstCompactorProphecy
            ->compact($file, 'original contents foo_value')
            ->willReturn($firstCompactorOutput = 'first compactor contents')
        ;

        $secondCompactorProphecy = $this->prophesize(Compactor::class);
        $secondCompactorProphecy
            ->compact($file, $firstCompactorOutput)
            ->willReturn($secondCompactorOutput = 'second compactor contents')
        ;

        $this->box->registerCompactors([
            $firstCompactorProphecy->reveal(),
            $secondCompactorProphecy->reveal(),
        ]);

        $this->box->registerPlaceholders($placeholderMapping);
        $this->box->addFile($file, $contents);

        $expectedContents = $secondCompactorOutput;
        $expectedPharPath = 'phar://test.phar/'.$file;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);

        $firstCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $secondCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_can_add_files_to_the_phar(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            dump_file($file, $contents);
        }

        $this->box->addFiles(['foo', 'bar'], false);

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_files_with_absolute_path_to_the_phar(): void
    {
        $files = [
            $f1 = $this->tmp.'/sub-dir/foo' => 'foo contents',
            $f2 = $this->tmp.'/sub-dir/bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            dump_file($file, $contents);
        }

        $this->box->addFiles([$f1, $f2], false);

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar'.str_replace($this->tmp, '', $file);

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_files_with_a_local_path_to_the_phar(): void
    {
        $fileMapper = new MapFile([
            ['' => 'local'],
        ]);

        $this->box->registerFileMapping($this->tmp, $fileMapper);

        $files = [
            'foo' => [
                'contents' => 'foo contents',
                'local' => 'local/foo',
            ],
            'bar' => [
                'contents' => 'bar contents',
                'local' => 'local/bar',
            ],
        ];

        foreach ($files as $file => $item) {
            dump_file($file, $item['contents']);
        }

        $this->box->addFiles(['foo', 'bar'], false);

        foreach ($files as $file => $item) {
            $expectedContents = $item['contents'];
            $expectedPharPath = 'phar://test.phar/'.$item['local'];

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_binary_files_to_the_phar(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            dump_file($file, $contents);
        }

        $this->box->addFiles(['foo', 'bar'], true);

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_binary_files_with_a_local_path_to_the_phar(): void
    {
        $fileMapper = new MapFile([
            ['' => 'local'],
        ]);

        $this->box->registerFileMapping($this->tmp, $fileMapper);

        $files = [
            'foo' => [
                'contents' => 'foo contents',
                'local' => 'local/foo',
            ],
            'bar' => [
                'contents' => 'bar contents',
                'local' => 'local/bar',
            ],
        ];

        foreach ($files as $file => $item) {
            dump_file($file, $item['contents']);
        }

        $this->box->addFiles(['foo', 'bar'], true);

        foreach ($files as $file => $item) {
            $expectedContents = $item['contents'];
            $expectedPharPath = 'phar://test.phar/'.$item['local'];

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_compacts_the_files_contents_and_replace_placeholders_before_adding_them_to_the_phar(): void
    {
        $files = [
            'foo' => '@foo_placeholder@',
            'bar' => '@bar_placeholder@',
        ];

        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value',
            '@bar_placeholder@' => 'bar_value',
        ];

        $this->box->registerPlaceholders($placeholderMapping);

        foreach ($files as $file => $contents) {
            dump_file($file, $contents);
        }

        // Cannot test the compactors: there is a bug with the serialization of the Prophecy objects which prevents
        // their correct serialization

        $this->box->addFiles(array_keys($files), false);

        $expected = [
            'foo' => 'foo_value',
            'bar' => 'bar_value',
        ];

        foreach ($expected as $file => $expectedContents) {
            $expectedPharPath = 'phar://test.phar/'.$file;

            $this->assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            $this->assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_maps_the_files_before_adding_it_to_the_phar(): void
    {
        $map = new MapFile([
            ['acme' => 'src/Foo'],
            ['' => 'lib'],
        ]);

        $files = [
            'acme/foo' => 'src/Foo/foo',
            'acme/bar' => 'src/Foo/bar',
            'f1' => 'lib/f1',
            'f2' => 'lib/f2',
        ];

        $this->box->registerFileMapping($this->tmp, $map);

        foreach ($files as $file => $expectedLocal) {
            dump_file($file);
        }

        $this->box->addFiles(array_keys($files), true);

        foreach ($files as $expectedLocal) {
            $this->assertFileExists(
                (string) $this->box->getPhar()[$expectedLocal],
                'Expected to find the file "%s" in the PHAR.'
            );

            $pathInPhar = str_replace(
                'phar://'.$this->box->getPhar()->getPath().'/',
                '',
                (string) $this->box->getPhar()[$expectedLocal]
            );

            $this->assertSame($expectedLocal, $pathInPhar);
        }
    }

    public function test_it_cannot_add_an_non_existent_files_to_the_phar(): void
    {
        try {
            $this->box->addFiles(['/nowhere/foo'], true);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/nowhere/foo" was expected to exist.',
                $exception->getMessage()
            );
            $this->assertSame(102, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_it_cannot_add_unreadable_files(): void
    {
        $file = 'foo';
        $contents = 'test';

        file_put_contents($file, $contents);
        chmod($file, 0355);

        try {
            $this->box->addFiles([$file], true);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                "Path \"${file}\" was expected to be readable.",
                $exception->getMessage()
            );
            $this->assertSame(103, $exception->getCode());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_it_exposes_the_underlying_PHAR(): void
    {
        $expected = new Phar('test.phar');
        $actual = $this->box->getPhar();

        $this->assertEquals($expected, $actual);
    }

    public function test_register_placeholders(): void
    {
        file_put_contents(
            $file = 'foo',
            <<<'PHP'
#!/usr/bin/env php
<?php

echo <<<EOF
Test replacing placeholders.

String value: @string_placeholder@
Int value: @int_placeholder@
Stringable value: @stringable_placeholder@

EOF;

__HALT_COMPILER();
PHP
        );

        $stringable = new class() {
            public function __toString(): string
            {
                return 'stringable value';
            }
        };

        $this->box->registerPlaceholders([
            '@string_placeholder@' => 'string value',
            '@int_placeholder@' => 10,
            '@stringable_placeholder@' => $stringable,
        ]);

        $this->box->registerStub($file, true);

        $expected = <<<'EOF'
Test replacing placeholders.

String value: string value
Int value: 10
Stringable value: stringable value
EOF;

        exec('php test.phar', $output);

        $actual = implode(PHP_EOL, $output);

        $this->assertSame($expected, $actual);
    }

    public function test_register_stub_file(): void
    {
        file_put_contents(
            $file = 'foo',
            <<<'STUB'
#!/usr/bin/env php
<?php

echo 'Hello world!';

__HALT_COMPILER();
STUB
        );

        $this->box->registerStub($file);

        $expected = <<<'STUB'
#!/usr/bin/env php
<?php

echo 'Hello world!';

__HALT_COMPILER(); ?>
STUB;

        $actual = trim($this->box->getPhar()->getStub());

        $this->assertSame($expected, $actual);

        $expectedOutput = 'Hello world!';
        $actualOutput = exec('php test.phar');

        $this->assertSame($expectedOutput, $actualOutput, 'Expected the PHAR to be executable.');
    }

    public function test_placeholders_are_also_replaced_in_stub_file(): void
    {
        file_put_contents(
            $file = 'foo',
            <<<'STUB'
#!/usr/bin/env php
<?php

echo '@message@';

__HALT_COMPILER();
STUB
        );

        $this->box->registerPlaceholders(['@message@' => 'Hello world!']);
        $this->box->registerStub($file);

        $expected = <<<'STUB'
#!/usr/bin/env php
<?php

echo 'Hello world!';

__HALT_COMPILER(); ?>
STUB;

        $actual = trim($this->box->getPhar()->getStub());

        $this->assertSame($expected, $actual);

        $expectedOutput = 'Hello world!';
        $actualOutput = exec('php test.phar');

        $this->assertSame($expectedOutput, $actualOutput, 'Expected the PHAR to be executable.');
    }

    public function test_cannot_set_non_existent_file_as_stub_file(): void
    {
        try {
            $this->box->registerStub('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_set_non_readable_file_as_stub_file(): void
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        try {
            $this->box->registerStub('vfs://test/test.php');

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'Path "vfs://test/test.php" was expected to be readable.',
                $exception->getMessage()
            );
        }
    }

    public function test_cannot_register_non_scalar_placeholders(): void
    {
        try {
            $this->box->registerPlaceholders(['stream' => STDOUT]);

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'Expected value "stream" to be a scalar or stringable object.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @requires extension openssl
     */
    public function test_it_can_sign_the_PHAR(): void
    {
        [$key, $password] = $this->getPrivateKey();

        $phar = $this->box->getPhar();

        $this->configureHelloWorldPhar();

        $this->box->sign($key, $password);

        $this->assertNotSame([], $phar->getSignature(), 'Expected the PHAR to be signed.');
        $this->assertInternalType('string', $phar->getSignature()['hash'], 'Expected the PHAR signature hash to be a string.');
        $this->assertNotEmpty($phar->getSignature()['hash'], 'Expected the PHAR signature hash to not be empty.');

        $this->assertSame('OpenSSL', $phar->getSignature()['hash_type']);

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable.'
        );
    }

    public function test_it_cannot_sign_if_cannot_get_the_private_key(): void
    {
        $key = 'Invalid key';
        $password = 'test';

        mkdir('test.phar.pubkey');

        $this->configureHelloWorldPhar();

        try {
            $this->box->sign($key, $password);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot create public key: "test.phar.pubkey" already exists and is not a file.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_sign_if_cannot_create_the_public_key(): void
    {
        [$key, $password] = $this->getPrivateKey();

        mkdir('test.phar.pubkey');

        $this->configureHelloWorldPhar();

        try {
            $this->box->sign($key, $password);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Cannot create public key: "test.phar.pubkey" already exists and is not a file.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @requires extension openssl
     */
    public function test_it_can_sign_the_PHAR_using_a_private_key_with_password(): void
    {
        $phar = $this->box->getPhar();

        [$key, $password] = $this->getPrivateKey();

        file_put_contents($file = 'foo', $key);

        $this->configureHelloWorldPhar();

        $this->box->signUsingFile($file, $password);

        $this->assertNotSame([], $phar->getSignature(), 'Expected the PHAR to be signed.');
        $this->assertInternalType('string', $phar->getSignature()['hash'], 'Expected the PHAR signature hash to be a string.');
        $this->assertNotEmpty($phar->getSignature()['hash'], 'Expected the PHAR signature hash to not be empty.');

        $this->assertSame('OpenSSL', $phar->getSignature()['hash_type']);

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected the PHAR to be executable.'
        );
    }

    public function test_it_cannot_sign_the_PHAR_with_a_non_existent_file_as_private_key(): void
    {
        try {
            $this->box->signUsingFile('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_sign_the_PHAR_with_an_unreadable_file_as_a_private_key(): void
    {
        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('private.key', 0000));

        vfsStreamWrapper::setRoot($root);

        try {
            $this->box->signUsingFile('vfs://test/private.key');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Path "vfs://test/private.key" was expected to be readable.',
                $exception->getMessage()
            );
        }
    }

    private function getPrivateKey(): array
    {
        return [
            <<<'KEY'
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: DES-EDE3-CBC,3FF97F75E5A8F534

TvEPC5L3OXjy4X5t6SRsW6J4Dfdgw0Mfjqwa4OOI88uk5L8SIezs4sHDYHba9GkG
RKVnRhA5F+gEHrabsQiVJdWPdS8xKUgpkvHqoAT8Zl5sAy/3e/EKZ+Bd2pS/t5yQ
aGGqliG4oWecx42QGL8rmyrbs2wnuBZmwQ6iIVIfYabwpiH+lcEmEoxomXjt9A3j
Sh8IhaDzMLnVS8egk1QvvhFjyXyBIW5mLIue6cdEgINbxzRReNQgjlyHS8BJRLp9
EvJcZDKJiNJt+VLncbfm4ZhbdKvSsbZbXC/Pqv06YNMY1+m9QwszHJexqjm7AyzB
MkBFedcxcxqvSb8DaGgQfUkm9rAmbmu+l1Dncd72Cjjf8fIfuodUmKsdfYds3h+n
Ss7K4YiiNp7u9pqJBMvUdtrVoSsNAo6i7uFa7JQTXec9sbFN1nezgq1FZmcfJYUZ
rdpc2J1hbHTfUZWtLZebA72GU63Y9zkZzbP3SjFUSWniEEbzWbPy2sAycHrpagND
itOQNHwZ2Me81MQQB55JOKblKkSha6cNo9nJjd8rpyo/lc/Iay9qlUyba7RO0V/t
wm9ZeUZL+D2/JQH7zGyLxkKqcMC+CFrNYnVh0U4nk3ftZsM+jcyfl7ScVFTKmcRc
ypcpLwfS6gyenTqiTiJx/Zca4xmRNA+Fy1EhkymxP3ku0kTU6qutT2tuYOjtz/rW
k6oIhMcpsXFdB3N9iHT4qqElo3rVW/qLQaNIqxd8+JmE5GkHmF43PhK3HX1PCmRC
TnvzVS0y1l8zCsRToUtv5rCBC+r8Q3gnvGGnT4jrsp98ithGIQCbbQ==
-----END RSA PRIVATE KEY-----
KEY
            ,
            'test',
        ];
    }

    private function configureHelloWorldPhar(): void
    {
        $this->box->getPhar()->addFromString(
            'main.php',
            <<<'PHP'
<?php

echo 'Hello, world!'.PHP_EOL;
PHP
        );

        $this->box->getPhar()->setStub(
            StubGenerator::create()
                ->index('main.php')
                ->generate()
        );
    }
}
