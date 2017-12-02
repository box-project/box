<?php

namespace KevinGH\Box;

use KevinGH\Box\Extract;
use Herrera\PHPUnit\TestCase;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit_Framework_Error_Warning;
use RuntimeException;
use Throwable;

class ExtractTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../fixtures/signature';
    
    public function getStubLengths()
    {
        return array(
            array(self::FIXTURES_DIR . '/example.phar', 203, null),
            array(self::FIXTURES_DIR . '/mixed.phar', 6683, "__HALT_COMPILER(); ?>"),
        );
    }

    public function testConstruct()
    {
        $extract = new Extract(__FILE__, 123);

        $this->assertEquals(
            __FILE__,
            $this->getPropertyValue($extract, 'file')
        );

        $this->assertSame(
            123,
            $this->getPropertyValue($extract, 'stub')
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
        $dir = $this->createDir();

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
        $path = self::FIXTURES_DIR . '/empty.phar';

        $extract = new Extract($path, Extract::findStubLength($path));

        $dir = $extract->go();

        $this->assertFileExists($dir . '/empty.php');

        $this->assertEquals('', file_get_contents($dir . '/empty.php'));
    }

    public function testPurge()
    {
        $dir = $this->createDir();

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

    protected function setUp()
    {
        $paths = array(
            sys_get_temp_dir() . '/pharextract/mixed'
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->purgePath($path);
            }
        }
    }
}
