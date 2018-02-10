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

use Box_Extract;
use Generator;
use InvalidArgumentException;
use KevinGH\Box\Test\FileSystemTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use RuntimeException;
use function KevinGH\Box\FileSystem\remove;

/**
 * @covers \Box_Extract
 */
class ExtractTest extends FileSystemTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../fixtures/signed_phars';

    /**
     * @var string
     */
    private $extractTmp;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->extractTmp = Box_Extract::getTmpDir();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        remove([$this->tmp, $this->extractTmp]);
    }

    public function test_it_cannot_be_created_for_a_non_existent_file(): void
    {
        try {
            new Box_Extract('/does/not/exist', 123);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @dataProvider provideStubLengths
     */
    public function test_it_can_find_the_stub_length(string $file, int $expectedLength, ?string $pattern): void
    {
        if (null !== $pattern) {
            $actual = Box_Extract::findStubLength($file, $pattern);
        } else {
            $actual = Box_Extract::findStubLength($file);
        }

        $this->assertSame($expectedLength, $actual);
    }

    public function test_it_cannot_find_the_stub_length_if_the_pattern_is_not_found(): void
    {
        $path = self::FIXTURES_DIR.'/example.phar';

        try {
            Box_Extract::findStubLength($path, 'bad pattern');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The pattern could not be found in "'.$path.'".',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_find_the_stub_length_of_non_existent_file(): void
    {
        try {
            Box_Extract::findStubLength('/does/not/exist');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'File "/does/not/exist" was expected to exist.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_cannot_find_the_stub_length_of_unreadable_file(): void
    {
        touch($file = 'unreadable_foo');
        chmod($file, 0355);

        try {
            Box_Extract::findStubLength($file);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Path "unreadable_foo" was expected to be readable.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_can_extract_the_PHAR(): void
    {
        $extract = new Box_Extract(self::FIXTURES_DIR.'/mixed.phar', 6683);

        $dir = $extract->go();

        $this->assertFileExists($dir);
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

    public function test_it_can_extract_the_PHAR_into_a_directory(): void
    {
        $extract = new Box_Extract(self::FIXTURES_DIR.'/mixed.phar', 6683);
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

    public function test_test_cannot_extract_the_PHAR_if_the_stub_length_is_invalid(): void
    {
        $path = self::FIXTURES_DIR.'/mixed.phar';

        $extract = new Box_Extract($path, -123);

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
     * @ticket https://github.com/box-project/box2-lib/issues/7
     *
     * Files with no content would trigger an exception when extracted.
     */
    public function test_it_can_extract_empty_PHARs(): void
    {
        $path = self::FIXTURES_DIR.'/empty.phar';

        $extract = new Box_Extract($path, Box_Extract::findStubLength($path));

        $dir = $extract->go();

        $this->assertFileExists($dir.'/empty.php');

        $this->assertSame('', file_get_contents($dir.'/empty.php'));
    }

    public function test_it_can_remove_the_extracted_PHAR(): void
    {
        mkdir($dir = 'foo');

        mkdir("$dir/a/b/c", 0755, true);
        touch("$dir/a/b/c/d");

        Box_Extract::purge($dir);

        $this->assertFileNotExists($dir);
    }

    public function test_it_throws_an_exception_if_cannot_purge_a_directory(): void
    {
        $root = vfsStream::newDirectory('test', 0444);
        $root->addChild(vfsStream::newFile('test', 0000));

        vfsStreamWrapper::setRoot($root);

        try {
            Box_Extract::purge('vfs://test/test');

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The file "vfs://test/test" could not be deleted.',
                $exception->getMessage()
            );
        }
    }

    public function provideStubLengths(): Generator
    {
        yield [self::FIXTURES_DIR.'/example.phar', 203, null];
        yield [self::FIXTURES_DIR.'/mixed.phar', 6683, '__HALT_COMPILER(); ?>'];
    }
}
