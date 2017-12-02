<?php

namespace KevinGH\Box;

use KevinGH\Box\Extract;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use RuntimeException;
use Throwable;

class ExtractTest extends TestCase
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
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmp = make_tmp_dir('box', __CLASS__);

        chdir($this->tmp);
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

    public function getStubLengths()
    {
        return array(
            array(self::FIXTURES_DIR . '/example.phar', 203, null),
            array(self::FIXTURES_DIR . '/mixed.phar', 6683, "__HALT_COMPILER(); ?>"),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The path "/does/not/exist" is not a file or does not exist.
     */
    public function testConstructNotExist()
    {
        new Extract('/does/not/exist', 123);
    }

    /**
     * @dataProvider getStubLengths
     */
    public function testFindStubLength($file, $length, $pattern)
    {
        if ($pattern) {
            $this->assertSame(
                $length,
                Extract::findStubLength($file, $pattern)
            );
        } else {
            $this->assertSame($length, Extract::findStubLength($file));
        }
    }

    public function testFindStubLengthInvalid()
    {
        $path = self::FIXTURES_DIR . '/example.phar';

        try {
            Extract::findStubLength($path, 'bad pattern');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The pattern could not be found in "' . $path . '".',
                $exception->getMessage()
            );
        }
    }

    public function testFindStubLengthOpenError()
    {
        try {
            Extract::findStubLength('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertRegExp(
                '/No such file or directory/',
                $exception->getMessage()
            );
        }
    }

    public function testGo()
    {
        $extract = new Extract(self::FIXTURES_DIR . '/mixed.phar', 6683);

        $dir = $extract->go();

        $this->assertFileExists("$dir/test");

        $this->assertEquals(
            "<?php\n\necho \"This is a gzip compressed line.\n\";",
            file_get_contents("$dir/gzip/a.php")
        );

        $this->assertEquals(
            "<?php\n\necho \"This is a bzip2 compressed line.\n\";",
            file_get_contents("$dir/bzip2/b.php")
        );

        $this->assertEquals(
            "<?php\n\necho \"This is not a compressed line.\n\";",
            file_get_contents("$dir/none/c.php")
        );
    }

    public function testGoWithDir()
    {
        $extract = new Extract(self::FIXTURES_DIR . '/mixed.phar', 6683);
        mkdir($dir = 'foo');

        $extract->go($dir);

        $this->assertFileExists("$dir/test");

        $this->assertEquals(
            "<?php\n\necho \"This is a gzip compressed line.\n\";",
            file_get_contents("$dir/gzip/a.php")
        );

        $this->assertEquals(
            "<?php\n\necho \"This is a bzip2 compressed line.\n\";",
            file_get_contents("$dir/bzip2/b.php")
        );

        $this->assertEquals(
            "<?php\n\necho \"This is not a compressed line.\n\";",
            file_get_contents("$dir/none/c.php")
        );
    }

    public function testGoInvalidLength()
    {
        $this->markTestSkipped('Check this one again later.');
        $path = self::FIXTURES_DIR . '/mixed.phar';

        $extract = new Extract($path, -123);

        try {
            $extract->go();

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Could not seek to -123 in the file "' . $path . '".',
                $exception->getMessage()
            );
        }
    }

    /**
     * Issue #7
     *
     * Files with no content would trigger an exception when extracted.
     */
    public function testGoEmptyFile()
    {
        $this->markTestSkipped('Check this one again later.');
        $path = self::FIXTURES_DIR . '/empty.phar';

        $extract = new Extract($path, Extract::findStubLength($path));

        $dir = $extract->go();

        $this->assertFileExists($dir . '/empty.php');

        $this->assertEquals('', file_get_contents($dir . '/empty.php'));
    }

    public function testPurge()
    {
        mkdir($dir = 'foo');

        mkdir("$dir/a/b/c", 0755, true);
        touch("$dir/a/b/c/d");

        Extract::purge($dir);

        $this->assertFileNotExists($dir);
    }

    public function testPurgeUnlinkError()
    {
        $root = vfsStream::newDirectory('test', 0444);
        $root->addChild(vfsStream::newFile('test', 0000));

        vfsStreamWrapper::setRoot($root);

        try {
            Extract::purge('vfs://test/test');

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The file "vfs://test/test" could not be deleted.',
                $exception->getMessage()
            );
        }
    }
}
