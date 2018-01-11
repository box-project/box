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

use ArrayIterator;
use function chmod;
use FilesystemIterator;
use Herrera\Annotations\Tokenizer;
use InvalidArgumentException;
use KevinGH\Box\Compactor;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Exception\FileException;
use KevinGH\Box\Exception\UnexpectedValueException;
use function mkdir;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function realpath;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function touch;

/**
 * @coversNothing
 */
class BoxTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../fixtures/signature';

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var string
     */
    protected $tmp;

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

        $this->phar = new Phar('test.phar');
        $this->box = new Box($this->phar, 'test.phar');

        $this->compactorProphecy = $this->prophesize(Compactor::class);
        $this->compactor = $this->compactorProphecy->reveal();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        unset($this->box, $this->phar);

        chdir($this->cwd);

        remove_dir($this->tmp);

        restore_error_handler();

        parent::tearDown();
    }

    public function getPrivateKey()
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

        $this->box->addFile($file, $localPath);

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
            '@foo_placeholder@' => 'foo_value'
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

        $this->box->setValues($placeholderMapping);
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

    public function test_it_can_add_a_file_from_string_to_the_phar(): void
    {
        $localPath = 'foo';
        $contents = 'test';

        $this->box->addFromString($localPath, $contents);

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);
    }

    public function test_it_compacts_the_contents_before_adding_it_to_the_phar(): void
    {
        $localPath = 'foo';
        $contents = 'original contents @foo_placeholder@';
        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value'
        ];

        file_put_contents($localPath, $contents);

        $firstCompactorProphecy = $this->prophesize(Compactor::class);
        $firstCompactorProphecy
            ->compact($localPath, 'original contents foo_value')
            ->willReturn($firstCompactorOutput = 'first compactor contents')
        ;

        $secondCompactorProphecy = $this->prophesize(Compactor::class);
        $secondCompactorProphecy
            ->compact($localPath, $firstCompactorOutput)
            ->willReturn($secondCompactorOutput = 'second compactor contents')
        ;

        $this->box->registerCompactors([
            $firstCompactorProphecy->reveal(),
            $secondCompactorProphecy->reveal(),
        ]);

        $this->box->setValues($placeholderMapping);
        $this->box->addFromString($localPath, $contents);

        $expectedContents = $secondCompactorOutput;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        $this->assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        $this->assertSame($expectedContents, $actualContents);

        $firstCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $secondCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function testGetPhar(): void
    {
        $this->assertSame($this->phar, $this->box->getPhar());
    }

    public function testGetSignature(): void
    {
        $path = self::FIXTURES_DIR.'/example.phar';
        $phar = new Phar($path);

        $this->assertEquals(
            $phar->getSignature(),
            Box::getSignature($path)
        );
    }

    public function testSetStubUsingFileNotExist(): void
    {
        $this->expectException(\KevinGH\Box\Exception\FileException::class);
        $this->expectExceptionMessage('The file "/does/not/exist" does not exist or is not a file.');

        $this->box->setStubUsingFile('/does/not/exist');
    }

    public function testSetStubUsingFileReadError(): void
    {
        $this->expectException(\KevinGH\Box\Exception\FileException::class);
        $this->expectExceptionMessage('failed to open stream');

        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        $this->box->setStubUsingFile('vfs://test/test.php');
    }

    public function testSetStubUsingFile(): void
    {
        touch($file = 'foo');

        file_put_contents(
            $file,
            <<<'STUB'
#!/usr/bin/env php
<?php
echo "@replace_me@";
__HALT_COMPILER();
STUB
        );

        $this->box->setValues(['@replace_me@' => 'replaced']);
        $this->box->setStubUsingFile($file, true);

        $this->assertSame(
            'replaced',
            exec('php test.phar')
        );
    }

    public function testSetValuesNonScalar(): void
    {
        $this->expectException(\KevinGH\Box\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-scalar values (such as resource) are not supported.');

        $this->box->setValues(['stream' => STDOUT]);
    }

    public function testSign(): void
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }

        list($key, $password) = $this->getPrivateKey();

        $this->box->getPhar()->addFromString(
            'test.php',
            '<?php echo "Hello, world!\n";'
        );

        $this->box->getPhar()->setStub(
            StubGenerator::create()
                ->index('test.php')
                ->generate()
        );

        $this->box->sign($key, $password);

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    public function testSignWriteError(): void
    {
        list($key, $password) = $this->getPrivateKey();

        mkdir('test.phar.pubkey');

        $this->box->getPhar()->addFromString('test.php', '<?php $test = 1;');

        try {
            $this->box->sign($key, $password);

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertRegExp(
                '/failed to open stream/',
                $exception->getMessage()
            );
        }
    }

    public function testSignUsingFile(): void
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }

        list($key, $password) = $this->getPrivateKey();

        touch($file = 'foo');

        file_put_contents($file, $key);

        $this->box->getPhar()->addFromString(
            'test.php',
            '<?php echo "Hello, world!\n";'
        );

        $this->box->getPhar()->setStub(
            StubGenerator::create()
                ->index('test.php')
                ->generate()
        );

        $this->box->signUsingFile($file, $password);

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    public function testSignUsingFileNotExist(): void
    {
        $this->expectException(\KevinGH\Box\Exception\FileException::class);
        $this->expectExceptionMessage('The file "/does/not/exist" does not exist or is not a file.');

        $this->box->signUsingFile('/does/not/exist');
    }

    public function testSignUsingFileReadError(): void
    {
        $this->expectException(\KevinGH\Box\Exception\FileException::class);
        $this->expectExceptionMessage('failed to open stream');

        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('private.key', 0000));

        vfsStreamWrapper::setRoot($root);

        $this->box->signUsingFile('vfs://test/private.key');
    }
}
