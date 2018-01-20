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

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @coversNothing
 */
class ExtractTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../fixtures/signed_phars';

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
        return [
            [self::FIXTURES_DIR.'/example.phar', 203, null],
            [self::FIXTURES_DIR.'/mixed.phar', 6683, '__HALT_COMPILER(); ?>'],
        ];
    }

    public function testConstructNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The path "/does/not/exist" is not a file or does not exist.');

        new Extract('/does/not/exist', 123);
    }

    /**
     * @dataProvider getStubLengths
     *
     * @param mixed $file
     * @param mixed $length
     * @param mixed $pattern
     */
    public function testFindStubLength($file, $length, $pattern): void
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

    public function testFindStubLengthInvalid(): void
    {
        $path = self::FIXTURES_DIR.'/example.phar';

        try {
            Extract::findStubLength($path, 'bad pattern');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The pattern could not be found in "'.$path.'".',
                $exception->getMessage()
            );
        }
    }

    public function testFindStubLengthOpenError(): void
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

    public function testGo(): void
    {
        $extract = new Extract(self::FIXTURES_DIR.'/mixed.phar', 6683);

        $dir = $extract->go();

        $this->assertFileExists("$dir/test");

        $this->assertSame(
            "<?php\n\necho \"This is a gzip compressed line.\n\";",
            file_get_contents("$dir/gzip/a.php")
        );

        $this->assertSame(
            "<?php\n\necho \"This is a bzip2 compressed line.\n\";",
            file_get_contents("$dir/bzip2/b.php")
        );

        $this->assertSame(
            "<?php\n\necho \"This is not a compressed line.\n\";",
            file_get_contents("$dir/none/c.php")
        );
    }

    public function testGoWithDir(): void
    {
        $extract = new Extract(self::FIXTURES_DIR.'/mixed.phar', 6683);
        mkdir($dir = 'foo');

        $extract->go($dir);

        $this->assertFileExists("$dir/test");

        $this->assertSame(
            "<?php\n\necho \"This is a gzip compressed line.\n\";",
            file_get_contents("$dir/gzip/a.php")
        );

        $this->assertSame(
            "<?php\n\necho \"This is a bzip2 compressed line.\n\";",
            file_get_contents("$dir/bzip2/b.php")
        );

        $this->assertSame(
            "<?php\n\necho \"This is not a compressed line.\n\";",
            file_get_contents("$dir/none/c.php")
        );
    }

    public function testGoInvalidLength(): void
    {
        $this->markTestSkipped('Check this one again later.');
        $path = self::FIXTURES_DIR.'/mixed.phar';

        $extract = new Extract($path, -123);

        try {
            $extract->go();

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Could not seek to -123 in the file "'.$path.'".',
                $exception->getMessage()
            );
        }
    }

    /**
     * Issue #7.
     *
     * Files with no content would trigger an exception when extracted.
     */
    public function testGoEmptyFile(): void
    {
        $this->markTestSkipped('Check this one again later.');
        $path = self::FIXTURES_DIR.'/empty.phar';

        $extract = new Extract($path, Extract::findStubLength($path));

        $dir = $extract->go();

        $this->assertFileExists($dir.'/empty.php');

        $this->assertSame('', file_get_contents($dir.'/empty.php'));
    }

    public function testPurge(): void
    {
        mkdir($dir = 'foo');

        mkdir("$dir/a/b/c", 0755, true);
        touch("$dir/a/b/c/d");

        Extract::purge($dir);

        $this->assertFileNotExists($dir);
    }

    public function testPurgeUnlinkError(): void
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
