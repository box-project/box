<?php

namespace KevinGH\Box;

use ArrayIterator;
use FilesystemIterator;
use Herrera\Annotations\Tokenizer;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\DummyCompactor;
use KevinGH\Box\Compactor\Php;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use KevinGH\Box\Exception\FileException;
use KevinGH\Box\Exception\UnexpectedValueException;

class BoxTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../fixtures/signature';

    /**
     * @var Box
     */
    private $box;

    /**
     * @var Phar
     */
    private $phar;

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var string
     */
    protected $tmp;

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
        return array(
            <<<KEY
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
            'test'
        );
    }

    public function testAddFile()
    {
        touch($file = 'foo');

        file_put_contents($file, 'test');

        $this->box->addFile($file, 'test/test.php');

        $this->assertEquals(
            'test',
            file_get_contents('phar://test.phar/test/test.php')
        );
    }

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     * @expectedExceptionMessage The file "/does/not/exist" does not exist or is not a file.
     */
    public function testAddFileNotExist()
    {
        $this->box->addFile('/does/not/exist');
    }

    public function testAddFileReadError()
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        try {
            $this->box->addFile('vfs://test/test.php');

            $this->fail('Expected exception to be thrown.');
        } catch (FileException $exception) {
            $this->assertRegExp(
                '/failed to open stream/',
                $exception->getMessage()
            );
        }
    }

    public function testAddFromString()
    {
        $original = <<<SOURCE
<?php

/**
 * My class.
 */
class @thing@
{
    /**
     * My method.
     */
    public function @other_thing@()
    {
    }
}
SOURCE;

        $expected = <<<SOURCE
<?php




class MyClass
{



public function myMethod()
{
}
}
SOURCE;

        $this->box->addCompactor(new Php(new Tokenizer()));
        $this->box->setValues(
            array(
                '@thing@' => 'MyClass',
                '@other_thing@' => 'myMethod'
            )
        );

        $this->box->addFromString('test/test.php', $original);

        $this->assertEquals(
            $expected,
            file_get_contents('phar://test.phar/test/test.php')
        );
    }

    public function testBuildFromDirectory()
    {
        mkdir('test/sub', 0755, true);
        touch('test/sub.txt');

        file_put_contents(
            'test/sub/test.php',
            '<?php echo "Hello, @name@!\n";'
        );

        $this->box->setValues(array('@name@' => 'world'));
        $this->box->buildFromDirectory($this->tmp, '/\.php$/');

        $this->assertFalse(isset($this->phar['test/sub.txt']));
        $this->assertEquals(
            '<?php echo "Hello, world!\n";',
            file_get_contents('phar://test.phar/test/sub/test.php')
        );
    }

    public function testBuildFromIterator()
    {
        mkdir('test/sub', 0755, true);

        file_put_contents(
            'test/sub/test.php',
            '<?php echo "Hello, @name@!\n";'
        );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->tmp,
                FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS
            )
        );

        $this->box->setValues(array('@name@' => 'world'));
        $this->box->buildFromIterator($iterator, $this->tmp);

        $this->assertEquals(
            '<?php echo "Hello, world!\n";',
            file_get_contents('phar://test.phar/test/sub/test.php')
        );
    }

    /**
     * @depends testBuildFromIterator
     */
    public function testBuildFromIteratorMixed()
    {
        mkdir('object');
        mkdir('string');

        touch('object.php');
        touch('string.php');

        $this->box->buildFromIterator(
            new ArrayIterator(
                array(
                    'object' => new SplFileInfo($this->tmp . '/object'),
                    'string' => $this->tmp . '/string',
                    'object.php' => new SplFileInfo($this->tmp . '/object.php'),
                    'string.php' => $this->tmp . '/string.php',
                )
            ),
            $this->tmp
        );

        /** @var $phar SplFileInfo[] */
        $phar = $this->phar;

        $this->assertTrue($phar['object']->isDir());
        $this->assertTrue($phar['string']->isDir());
        $this->assertTrue($phar['object.php']->isFile());
        $this->assertTrue($phar['string.php']->isFile());
    }

    /**
     * @expectedException \KevinGH\Box\Exception\InvalidArgumentException
     * @expectedExceptionMessage The $base argument is required for SplFileInfo values.
     */
    public function testBuildFromIteratorBaseRequired()
    {
        $this->box->buildFromIterator(
            new ArrayIterator(array(new SplFileInfo($this->tmp)))
        );
    }

    public function testBuildFromIteratorOutsideBase()
    {
        try {
            $this->box->buildFromIterator(
                new ArrayIterator(array(new SplFileInfo($this->tmp))),
                __DIR__
            );

            $this->fail('Expected exception to be thrown.');
        } catch (UnexpectedValueException $exception) {
            $this->assertSame(
                "The file \"{$this->tmp}\" is not in the base directory.",
                $exception->getMessage()
            );
        }
    }

    /**
     * @expectedException \KevinGH\Box\Exception\UnexpectedValueException
     * @expectedExceptionMessage The key returned by the iterator (integer) is not a string.
     */
    public function testBuildFromIteratorInvalidKey()
    {
        $this->box->buildFromIterator(new ArrayIterator(array('test')));
    }

    /**
     * @expectedException \KevinGH\Box\Exception\UnexpectedValueException
     * @expectedExceptionMessage The iterator value "resource" was not expected.
     */
    public function testBuildFromIteratorInvalid()
    {
        $this->box->buildFromIterator(
            new ArrayIterator(array('stream' => STDOUT))
        );
    }

    public function testCompactContents()
    {
        $this->box->addCompactor($this->compactor);

        $contents = ' my value ';
        $expected = 'my value';

        $this->compactorProphecy->supports('test.php')->willReturn(true);
        $this->compactorProphecy->compact($contents)->willReturn($expected);

        $actual = $this->box->compactContents('test.php', $contents);

        $this->assertSame($expected, $actual);

        $this->compactorProphecy->supports(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $this->compactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function testGetPhar()
    {
        $this->assertSame($this->phar, $this->box->getPhar());
    }

    public function testGetSignature()
    {
        $path = self::FIXTURES_DIR . '/example.phar';
        $phar = new Phar($path);

        $this->assertEquals(
            $phar->getSignature(),
            Box::getSignature($path)
        );
    }

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     * @expectedExceptionMessage The file "/does/not/exist" does not exist or is not a file.
     */
    public function testSetStubUsingFileNotExist()
    {
        $this->box->setStubUsingFile('/does/not/exist');
    }

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     * @expectedExceptionMessage failed to open stream
     */
    public function testSetStubUsingFileReadError()
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        $this->box->setStubUsingFile('vfs://test/test.php');
    }

    public function testSetStubUsingFile()
    {
        touch($file = 'foo');

        file_put_contents(
            $file,
            <<<STUB
#!/usr/bin/env php
<?php
echo "@replace_me@";
__HALT_COMPILER();
STUB
        );

        $this->box->setValues(array('@replace_me@' => 'replaced'));
        $this->box->setStubUsingFile($file, true);

        $this->assertEquals(
            'replaced',
            exec('php test.phar')
        );
    }

    /**
     * @expectedException \KevinGH\Box\Exception\InvalidArgumentException
     * @expectedExceptionMessage Non-scalar values (such as resource) are not supported.
     */
    public function testSetValuesNonScalar()
    {
        $this->box->setValues(array('stream' => STDOUT));
    }

    /**
     * @depends testGetPhar
     */
    public function testSign()
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

        $this->assertEquals(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    /**
     * @depends testSign
     */
    public function testSignWriteError()
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

    /**
     * @depends testSign
     */
    public function testSignUsingFile()
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

        $this->assertEquals(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     * @expectedExceptionMessage The file "/does/not/exist" does not exist or is not a file.
     */
    public function testSignUsingFileNotExist()
    {
        $this->box->signUsingFile('/does/not/exist');
    }

    /**
     * @expectedException \KevinGH\Box\Exception\FileException
     * @expectedExceptionMessage failed to open stream
     */
    public function testSignUsingFileReadError()
    {
        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('private.key', 0000));

        vfsStreamWrapper::setRoot($root);

        $this->box->signUsingFile('vfs://test/private.key');
    }
}
