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

use Exception;
use InvalidArgumentException;
use KevinGH\Box\Compactor\FakeCompactor;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \KevinGH\Box\Box
 */
class BoxTest extends TestCase
{
    /**
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $tmp;

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
        $this->cwd = getcwd();
        $this->tmp = make_tmp_dir('box', __CLASS__);

        chdir($this->tmp);

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
        unset($this->box);

        chdir($this->cwd);

        remove_dir($this->tmp);

        //TODO: see if we need a custom error handler still
        restore_error_handler();

        parent::tearDown();
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
        mkdir('path-to');

        $file = $this->tmp.'/path-to/foo';
        $contents = 'test';

        file_put_contents($file, $contents);

        $this->box->addFile(realpath($file));

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar'.$file;

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

        $basePathRetriever = new RetrieveRelativeBasePath(realpath(dirname('test.phar')));
        $fileMapper = new MapFile([
            [$file => $localPath],
        ]);

        $this->box->registerFileMapping($basePathRetriever, $fileMapper);

        $this->box->addFile($file);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

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

        $basePathRetriever = new RetrieveRelativeBasePath(realpath(dirname('test.phar')));
        $fileMapper = new MapFile([
            [$file => $localPath],
        ]);

        $this->box->registerFileMapping($basePathRetriever, $fileMapper);

        $this->box->addFile($file, null, true);

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

    public function test_it_cannot_add_a_file_it_fails_to_read(): void
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
        list($key, $password) = $this->getPrivateKey();

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
        } catch (Exception $exception) {
            $this->assertSame(
                'error:0906D06C:PEM routines:PEM_read_bio:no start line',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_sign_if_cannot_create_the_public_key(): void
    {
        list($key, $password) = $this->getPrivateKey();

        mkdir('test.phar.pubkey');

        $this->configureHelloWorldPhar();

        try {
            $this->box->sign($key, $password);

            $this->fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(
                'Undefined index: code',
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

        list($key, $password) = $this->getPrivateKey();

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
