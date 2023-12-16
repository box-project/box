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

use Error;
use Exception;
use Fidry\Console\DisplayNormalizer;
use Fidry\FileSystem\FS;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Compactor\FakeCompactor;
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Test\FileSystemTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use PharFileInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use RuntimeException;
use SplFileInfo;
use Stringable;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Throwable;
use function array_filter;
use function array_keys;
use function current;
use function dirname;
use function exec;
use function extension_loaded;
use function file_get_contents;
use function implode;
use function in_array;
use function iterator_to_array;
use function Safe\realpath;
use function sprintf;
use function str_replace;
use function trim;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const STDOUT;

/**
 * @internal
 */
#[CoversClass(Box::class)]
#[RunTestsInSeparateProcesses]
class BoxTest extends FileSystemTestCase
{
    use ProphecyTrait;
    use RequiresPharReadonlyOff;

    private Box $box;

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();

        $this->box = Box::create('test.phar');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (false !== $pharPath = $this->box->getPhar()->getRealPath()) {
            Phar::unlinkArchive($pharPath);
        }

        unset($this->box);
    }

    public function test_it_cannot_start_the_buffering_if_it_is_already_started(): void
    {
        $this->box->startBuffering();

        try {
            $this->box->startBuffering();

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The buffering must be ended before starting it again',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_end_the_buffering_if_it_is_already_ended(): void
    {
        try {
            $this->box->endBuffering(Noop::create());

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The buffering must be started before ending it',
                $exception->getMessage(),
            );
        }

        $this->box->startBuffering();
        $this->box->endBuffering(Noop::create());

        try {
            $this->box->endBuffering(Noop::create());

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The buffering must be started before ending it',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_can_add_a_file_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, $contents);

        $this->box->startBuffering();
        $this->box->addFile($file);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_ensures_a_phar_cannot_be_empty(): void
    {
        $this->box->startBuffering();
        $this->box->endBuffering(Noop::create());

        $expectedPharPath = 'phar://test.phar/.box_empty';

        self::assertFileExists($expectedPharPath);
    }

    public function test_it_can_add_a_file_to_the_phar_in_a_new_buffering_process_does_not_remove_the_previous_files(): void
    {
        $file0 = 'file0';
        $file0Contents = 'file0 contents';

        $this->box->startBuffering();
        $this->box->addFile($file0, $file0Contents);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $file0Contents;
        $expectedPharPath = 'phar://test.phar/'.$file0;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);

        $file1 = 'file1';
        $file1Contents = 'file1 contents';

        $this->box->startBuffering();
        $this->box->addFile($file1, $file1Contents);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $file1Contents;
        $expectedPharPath = 'phar://test.phar/'.$file1;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_cannot_add_a_file_to_the_phar_if_the_buffering_did_not_start(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, $contents);

        try {
            $this->box->addFile($file);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot add files if the buffering has not started.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_can_add_a_non_existent_file_with_contents_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        $this->box->startBuffering();
        $this->box->addFile($file, $contents);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_an_existent_file_with_contents_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, 'tset');

        $this->box->startBuffering();
        $this->box->addFile($file, $contents);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_non_existent_bin_file_with_contents_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        $this->box->startBuffering();
        $this->box->addFile($file, $contents, true);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_an_existent_bin_file_with_contents_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, 'tset');

        $this->box->startBuffering();
        $this->box->addFile($file, $contents, true);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_file_with_absolute_path_to_the_phar(): void
    {
        $relativePath = 'path-to/foo';
        $file = Path::canonicalize($this->tmp.DIRECTORY_SEPARATOR.$relativePath);
        $contents = 'test';

        FS::dumpFile($file, $contents);

        $this->box->startBuffering();
        $this->box->addFile($file);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$relativePath;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_file_with_a_local_path_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';
        $localPath = 'local/path/foo';

        FS::dumpFile($file, $contents);

        $fileMapper = new MapFile(
            $this->tmp,
            [
                [$file => $localPath],
            ],
        );

        $this->box->registerFileMapping($fileMapper);

        $this->box->startBuffering();
        $this->box->addFile($file);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_binary_file_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, $contents);

        $this->box->registerCompactors(new Compactors(new FakeCompactor()));

        $this->box->startBuffering();
        $this->box->addFile($file, null, true);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_can_add_a_binary_file_with_a_local_path_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'test';
        $localPath = 'local/path/foo';

        FS::dumpFile($file, $contents);

        $fileMapper = new MapFile(
            $this->tmp,
            [
                [$file => $localPath],
            ],
        );

        $this->box->registerFileMapping($fileMapper);

        $this->box->startBuffering();
        $this->box->addFile($file, null, true);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $contents;
        $expectedPharPath = 'phar://test.phar/'.$localPath;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);
    }

    public function test_it_compacts_the_file_content_and_replace_placeholders_before_adding_it_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'original contents @foo_placeholder@';
        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value',
        ];

        FS::dumpFile($file, $contents);

        /** @var Compactor|ObjectProphecy $firstCompactorProphecy */
        $firstCompactorProphecy = $this->prophesize(Compactor::class);
        $firstCompactorProphecy
            ->compact($file, 'original contents foo_value')
            ->willReturn($firstCompactorOutput = 'first compactor contents');

        /** @var Compactor|ObjectProphecy $secondCompactorProphecy */
        $secondCompactorProphecy = $this->prophesize(Compactor::class);
        $secondCompactorProphecy
            ->compact($file, $firstCompactorOutput)
            ->willReturn($secondCompactorOutput = 'second compactor contents');

        $this->box->registerCompactors(
            new Compactors(
                $firstCompactorProphecy->reveal(),
                $secondCompactorProphecy->reveal(),
            ),
        );

        $this->box->registerPlaceholders($placeholderMapping);

        $this->box->startBuffering();
        $this->box->addFile($file);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $secondCompactorOutput;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);

        $firstCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $secondCompactorProphecy->compact(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    public function test_it_maps_the_file_before_adding_it_to_the_phar(): void
    {
        $map = new MapFile(
            $this->tmp,
            [
                ['acme' => 'src/Foo'],
                ['' => 'lib'],
            ],
        );

        $files = [
            'acme/foo' => 'src/Foo/foo',
            'acme/bar' => 'src/Foo/bar',
            'f1' => 'lib/f1',
            'f2' => 'lib/f2',
        ];

        $this->box->registerFileMapping($map);

        foreach ($files as $file => $expectedLocal) {
            FS::dumpFile($file);

            $this->box->startBuffering();
            $local = $this->box->addFile($file);
            $this->box->endBuffering(Noop::create());

            self::assertSame($expectedLocal, $local);

            self::assertFileExists(
                (string) $this->box->getPhar()[$local],
                'Expected to find the file "%s" in the PHAR.',
            );

            $pathInPhar = str_replace(
                'phar://'.$this->box->getPhar()->getPath().'/',
                '',
                (string) $this->box->getPhar()[$local],
            );

            self::assertSame($expectedLocal, $pathInPhar);
        }
    }

    public function test_it_cannot_add_an_non_existent_file_to_the_phar(): void
    {
        try {
            $this->box->startBuffering();
            $this->box->addFile('/nowhere/foo');

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "/nowhere/foo" does not exist.',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    public function test_it_cannot_add_an_unreadable_file(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, $contents);
        FS::chmod($file, 0o355);

        try {
            $this->box->startBuffering();
            $this->box->addFile($file);
            $this->box->endBuffering(Noop::create());

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The path "foo" is not readable.',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    public function test_it_compacts_the_contents_before_adding_it_to_the_phar(): void
    {
        $file = 'foo';
        $contents = 'original contents @foo_placeholder@';
        $placeholderMapping = [
            '@foo_placeholder@' => 'foo_value',
        ];

        FS::dumpFile($file, $contents);

        $firstCompactorProphecy = $this->prophesize(Compactor::class);
        $firstCompactorProphecy
            ->compact($file, 'original contents foo_value')
            ->willReturn($firstCompactorOutput = 'first compactor contents');

        $secondCompactorProphecy = $this->prophesize(Compactor::class);
        $secondCompactorProphecy
            ->compact($file, $firstCompactorOutput)
            ->willReturn($secondCompactorOutput = 'second compactor contents');

        $this->box->registerCompactors(
            new Compactors(
                $firstCompactorProphecy->reveal(),
                $secondCompactorProphecy->reveal(),
            ),
        );

        $this->box->registerPlaceholders($placeholderMapping);

        $this->box->startBuffering();
        $this->box->addFile($file, $contents);
        $this->box->endBuffering(Noop::create());

        $expectedContents = $secondCompactorOutput;
        $expectedPharPath = 'phar://test.phar/'.$file;

        self::assertFileExists($expectedPharPath);

        $actualContents = file_get_contents($expectedPharPath);

        self::assertSame($expectedContents, $actualContents);

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
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles(['foo', 'bar'], false);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_cannot_add_files_to_the_phar_if_the_buffering_did_not_start(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        try {
            $this->box->addFiles(['foo', 'bar'], false);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Cannot add files if the buffering has not started.', $exception->getMessage());
        }
    }

    public function test_it_can_add_files_with_absolute_path_to_the_phar(): void
    {
        $files = [
            $f1 = $this->tmp.'/sub-dir/foo' => 'foo contents',
            $f2 = $this->tmp.'/sub-dir/bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles([$f1, $f2], false);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar'.str_replace($this->tmp, '', $file);

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_files_with_a_local_path_to_the_phar(): void
    {
        $fileMapper = new MapFile(
            $this->tmp,
            [
                ['' => 'local'],
            ],
        );

        $this->box->registerFileMapping($fileMapper);

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
            FS::dumpFile($file, $item['contents']);
        }

        $this->box->startBuffering();
        $this->box->addFiles(['foo', 'bar'], false);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $item) {
            $expectedContents = $item['contents'];
            $expectedPharPath = 'phar://test.phar/'.$item['local'];

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_dump_the_autoloader_when_adding_files_to_the_phar(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
            'composer.json' => '{}',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $autoloadDumped = false;

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), false);
        $this->box->endBuffering(static function () use (&$autoloadDumped): void {
            $autoloadDumped = true;
        });

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }

        self::assertTrue($autoloadDumped);
    }

    public function test_it_artefacts_created_when_dumping_the_autoloader_are_added_to_the_phar(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
            'composer.json' => '{}',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), false);
        $this->box->endBuffering(static function () use (&$files): void {
            FS::dumpFile('zip');
            $files['zip'] = '';

            FS::dumpFile('zap');
            $files['zap'] = '';
        });

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_remove_the_composer_files(): void
    {
        $files = [
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'vendor/composer/installed.json' => '{}',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            self::assertFileExists('phar://test.phar/'.$file);
        }

        $this->box->removeComposerArtifacts('vendor');

        foreach ($files as $file => $contents) {
            self::assertFileDoesNotExist('phar://test.phar/'.$file);
        }
    }

    public function test_it_can_remove_the_composer_files_with_a_custom_vendor_directory(): void
    {
        $files = [
            'composer.json' => <<<'JSON'
                {
                    "config": {
                        "vendor-dir": "my-vendor"
                    }
                }
                JSON,
            'composer.lock' => '{}',
            'my-vendor/composer/installed.json' => '{}',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            self::assertFileExists('phar://test.phar/'.$file);
        }

        $this->box->removeComposerArtifacts('my-vendor');

        foreach ($files as $file => $contents) {
            self::assertFileDoesNotExist('phar://test.phar/'.$file);
        }
    }

    public function test_it_can_remove_the_composer_files_mapped_with_a_different_path(): void
    {
        $files = [
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'vendor/composer/installed.json' => '{}',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $map = new MapFile(
            $this->tmp,
            [
                ['' => 'lib'],
            ],
        );

        $this->box->registerFileMapping($map);

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            self::assertFileExists('phar://test.phar/lib/'.$file);
        }

        $this->box->removeComposerArtifacts('vendor');

        foreach ($files as $file => $contents) {
            self::assertFileDoesNotExist('phar://test.phar/lib/'.$file);
        }
    }

    public function test_it_cannot_remove_the_composer_files_when_buffering(): void
    {
        $this->box->startBuffering();

        try {
            $this->box->removeComposerArtifacts('vendor');

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The buffering must have ended before removing the Composer artefacts',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_bails_out_when_the_autoloader_failed_to_be_dumped(): void
    {
        $this->box->startBuffering();
        $this->box->addFile(
            'composer.json',
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["unknown"]
                    }
                }
                JSON,
        );

        $error = null;

        try {
            $this->box->endBuffering(static function () use (&$error): never {
                throw $error = new Error('Autoload dump error');
            });

            self::fail('Expected exception to be thrown.');
        } catch (Throwable $throwable) {
            self::assertSame($error, $throwable);
        }
    }

    public function test_it_can_add_binary_files_to_the_phar(): void
    {
        $files = [
            'foo' => 'foo contents',
            'bar' => 'bar contents',
        ];

        foreach ($files as $file => $contents) {
            FS::dumpFile($file, $contents);
        }

        $this->box->startBuffering();
        $this->box->addFiles(['foo', 'bar'], true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $contents) {
            $expectedContents = $contents;
            $expectedPharPath = 'phar://test.phar/'.$file;

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_can_add_binary_files_with_a_local_path_to_the_phar(): void
    {
        $fileMapper = new MapFile(
            $this->tmp,
            [
                ['' => 'local'],
            ],
        );

        $this->box->registerFileMapping($fileMapper);

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
            FS::dumpFile($file, $item['contents']);
        }

        $this->box->startBuffering();
        $this->box->addFiles(['foo', 'bar'], true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $file => $item) {
            $expectedContents = $item['contents'];
            $expectedPharPath = 'phar://test.phar/'.$item['local'];

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
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
            FS::dumpFile($file, $contents);
        }

        // Cannot test the compactors: there is a bug with the serialization of the Prophecy objects which prevents
        // their correct serialization

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), false);
        $this->box->endBuffering(Noop::create());

        $expected = [
            'foo' => 'foo_value',
            'bar' => 'bar_value',
        ];

        foreach ($expected as $file => $expectedContents) {
            $expectedPharPath = 'phar://test.phar/'.$file;

            self::assertFileExists($expectedPharPath);

            $actualContents = file_get_contents($expectedPharPath);

            self::assertSame($expectedContents, $actualContents);
        }
    }

    public function test_it_maps_the_files_before_adding_it_to_the_phar(): void
    {
        $map = new MapFile(
            $this->tmp,
            [
                ['acme' => 'src/Foo'],
                ['' => 'lib'],
            ],
        );

        $files = [
            'acme/foo' => 'src/Foo/foo',
            'acme/bar' => 'src/Foo/bar',
            'f1' => 'lib/f1',
            'f2' => 'lib/f2',
        ];

        $this->box->registerFileMapping($map);

        foreach ($files as $file => $expectedLocal) {
            FS::dumpFile($file);
        }

        $this->box->startBuffering();
        $this->box->addFiles(array_keys($files), true);
        $this->box->endBuffering(Noop::create());

        foreach ($files as $expectedLocal) {
            self::assertFileExists(
                (string) $this->box->getPhar()[$expectedLocal],
                'Expected to find the file "%s" in the PHAR.',
            );

            $pathInPhar = str_replace(
                'phar://'.$this->box->getPhar()->getPath().'/',
                '',
                (string) $this->box->getPhar()[$expectedLocal],
            );

            self::assertSame($expectedLocal, $pathInPhar);
        }
    }

    public function test_it_cannot_add_an_non_existent_files_to_the_phar(): void
    {
        try {
            $this->box->startBuffering();
            $this->box->addFiles(['/nowhere/foo'], true);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "/nowhere/foo" does not exist.',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    public function test_it_cannot_add_unreadable_files(): void
    {
        $file = 'foo';
        $contents = 'test';

        FS::dumpFile($file, $contents);
        FS::chmod($file, 0o355);

        try {
            $this->box->startBuffering();
            $this->box->addFiles([$file], true);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The path "foo" is not readable.',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }

    public function test_the_temporary_directory_created_for_box_is_removed_upon_failure(): void
    {
        $boxTmp = $this->tmp.DIRECTORY_SEPARATOR.'test-tmp-dir';

        try {
            $this->box->startBuffering();
            $this->box->addFiles(['/nowhere/foo'], false);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException) {
            $tmpDirs = iterator_to_array(
                Finder::create()
                    ->directories()
                    ->depth(0)
                    ->in(dirname($boxTmp)),
            );

            $boxDir = current(
                array_filter(
                    $tmpDirs,
                    fn (SplFileInfo $fileInfo): bool => false === in_array(
                        $fileInfo->getRealPath(),
                        [realpath($boxTmp), realpath($this->tmp)],
                        true,
                    ),
                ),
            );

            self::assertFalse(
                $boxDir,
                sprintf(
                    'Did not expect to find the directory "%s".',
                    $boxDir,
                ),
            );
        }
    }

    public function test_it_exposes_the_underlying_phar(): void
    {
        $expected = new Phar('test.phar');
        $actual = $this->box->getPhar();

        self::assertEquals($expected, $actual);
    }

    public function test_register_placeholders(): void
    {
        FS::dumpFile(
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
                PHP,
        );

        $stringable = new class() implements Stringable {
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

        $this->box->registerStub($file);

        $expected = <<<'EOF'
            Test replacing placeholders.

            String value: string value
            Int value: 10
            Stringable value: stringable value
            EOF;

        exec('php test.phar', $output);

        $actual = implode(PHP_EOL, $output);

        self::assertSame($expected, $actual);
    }

    public function test_register_stub_file(): void
    {
        FS::dumpFile(
            $file = 'foo',
            <<<'STUB'
                #!/usr/bin/env php
                <?php

                echo 'Hello world!';

                __HALT_COMPILER();
                STUB,
        );

        $this->box->registerStub($file);

        $expected = <<<'STUB'
            #!/usr/bin/env php
            <?php

            echo 'Hello world!';

            __HALT_COMPILER(); ?>
            STUB;

        $actual = trim($this->box->getPhar()->getStub());

        self::assertSame($expected, $actual);

        $expectedOutput = 'Hello world!';
        $actualOutput = exec('php test.phar');

        self::assertSame($expectedOutput, $actualOutput, 'Expected the PHAR to be executable.');
    }

    public function test_placeholders_are_also_replaced_in_stub_file(): void
    {
        FS::dumpFile(
            $file = 'foo',
            <<<'STUB'
                #!/usr/bin/env php
                <?php

                echo '@message@';

                __HALT_COMPILER();
                STUB,
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

        self::assertSame($expected, $actual);

        $expectedOutput = 'Hello world!';
        $actualOutput = exec('php test.phar');

        self::assertSame($expectedOutput, $actualOutput, 'Expected the PHAR to be executable.');
    }

    public function test_cannot_set_non_existent_file_as_stub_file(): void
    {
        try {
            $this->box->registerStub('/does/not/exist');

            self::fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            self::assertSame(
                'The file "/does/not/exist" does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_set_non_readable_file_as_stub_file(): void
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0));

        try {
            $this->box->registerStub('vfs://test/test.php');

            self::fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            self::assertSame(
                'The path "vfs://test/test.php" is not readable.',
                $exception->getMessage(),
            );
        }
    }

    public function test_cannot_register_non_scalar_placeholders(): void
    {
        try {
            $this->box->registerPlaceholders(['stream' => STDOUT]);

            self::fail('Expected exception to be thrown.');
        } catch (Exception $exception) {
            self::assertSame(
                'Expected value "resource" to be a scalar or stringable object.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_compress_the_phar_while_buffering(): void
    {
        try {
            $this->box->startBuffering();
            $this->box->compress(CompressionAlgorithm::NONE);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot compress files while buffering.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_compress_if_the_required_extension_is_not_loaded(): void
    {
        if (extension_loaded('bz2')) {
            self::markTestSkipped('Requires the extension bz2 to not be loaded.');
        }

        try {
            $this->box->compress(CompressionAlgorithm::BZ2);

            self::fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Cannot compress the PHAR with the compression algorithm "BZ2": the extension "bz2" is required but appear to not '
                .'be loaded',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @requires extension zlib
     */
    public function test_it_compresses_all_the_files_with_the_given_algorithm(): void
    {
        $this->box->startBuffering();

        $this->box->addFile('file0', 'file0 contents');
        $this->box->addFile('file1', 'file1 contents');
        $this->box->getPhar()->setStub(
            $stub = <<<'PHP'
                <?php

                echo 'Yo';

                __HALT_COMPILER(); ?>

                PHP,
        );

        $this->box->endBuffering(Noop::create());

        self::assertCount(2, $this->box);

        /** @var PharFileInfo[] $files */
        $files = [
            $this->box->getPhar()['file0'],
            $this->box->getPhar()['file1'],
        ];

        foreach ($files as $file) {
            self::assertFalse($file->isCompressed());
        }

        self::assertSame(
            $stub,
            DisplayNormalizer::removeTrailingSpaces($this->box->getPhar()->getStub()),
            'Did not expect the stub to be affected by the compression.',
        );

        $extensionRequired = $this->box->compress(CompressionAlgorithm::GZ);

        self::assertSame('zlib', $extensionRequired);

        /** @var PharFileInfo[] $files */
        $files = [
            $this->box->getPhar()['file0'],
            $this->box->getPhar()['file1'],
        ];

        foreach ($files as $file) {
            self::assertTrue($file->isCompressed(Phar::GZ));
        }

        self::assertSame(
            $stub,
            DisplayNormalizer::removeTrailingSpaces($this->box->getPhar()->getStub()),
            'Did not expect the stub to be affected by the compression.',
        );

        $extensionRequired = $this->box->compress(CompressionAlgorithm::NONE);

        self::assertNull($extensionRequired);

        /** @var PharFileInfo[] $files */
        $files = [
            $this->box->getPhar()['file0'],
            $this->box->getPhar()['file1'],
        ];

        foreach ($files as $file) {
            self::assertFalse($file->isCompressed());
        }

        self::assertSame(
            $stub,
            DisplayNormalizer::removeTrailingSpaces($this->box->getPhar()->getStub()),
            'Did not expect the stub to be affected by the compression.',
        );
    }

    /**
     * @requires extension openssl
     */
    public function test_it_can_sign_the_phar(): void
    {
        [$key, $password] = $this->getPrivateKey();

        $phar = $this->box->getPhar();

        $this->configureHelloWorldPhar();

        $this->box->signUsingKey($key, $password);

        self::assertNotSame([], $phar->getSignature(), 'Expected the PHAR to be signed.');
        self::assertIsString($phar->getSignature()['hash'], 'Expected the PHAR signature hash to be a string.');
        self::assertNotEmpty($phar->getSignature()['hash'], 'Expected the PHAR signature hash to not be empty.');

        self::assertSame('OpenSSL', $phar->getSignature()['hash_type']);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable.',
        );
    }

    public function test_it_cannot_sign_if_cannot_get_the_private_key(): void
    {
        $key = 'Invalid key';
        $password = 'test';

        FS::mkdir('test.phar.pubkey');

        $this->configureHelloWorldPhar();

        try {
            $this->box->signUsingKey($key, $password);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot create public key: "test.phar.pubkey" already exists and is not a file.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_sign_if_cannot_create_the_public_key(): void
    {
        [$key, $password] = $this->getPrivateKey();

        FS::mkdir('test.phar.pubkey');

        $this->configureHelloWorldPhar();

        try {
            $this->box->signUsingKey($key, $password);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Cannot create public key: "test.phar.pubkey" already exists and is not a file.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @requires extension openssl
     */
    public function test_it_can_sign_the_phar_using_a_private_key_with_password(): void
    {
        $phar = $this->box->getPhar();

        [$key, $password] = $this->getPrivateKey();

        FS::dumpFile($file = 'foo', $key);

        $this->configureHelloWorldPhar();

        $this->box->signUsingFile($file, $password);

        self::assertNotSame([], $phar->getSignature(), 'Expected the PHAR to be signed.');
        self::assertIsString($phar->getSignature()['hash'], 'Expected the PHAR signature hash to be a string.');
        self::assertNotEmpty($phar->getSignature()['hash'], 'Expected the PHAR signature hash to not be empty.');

        self::assertSame('OpenSSL', $phar->getSignature()['hash_type']);

        self::assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected the PHAR to be executable.',
        );
    }

    /**
     * @requires extension openssl
     */
    public function test_it_cannot_sign_the_phar_using_a_private_key_with_the_wrong_password(): void
    {
        $key = $this->getPrivateKey()[0];
        $password = 'wrong password';

        FS::dumpFile($file = 'foo', $key);

        $this->configureHelloWorldPhar();

        try {
            $this->box->signUsingFile($file, $password);

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Could not retrieve the private key, check that the password is correct.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_sign_the_phar_with_a_non_existent_file_as_private_key(): void
    {
        try {
            $this->box->signUsingFile('/does/not/exist');

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The file "/does/not/exist" does not exist.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_cannot_sign_the_phar_with_an_unreadable_file_as_a_private_key(): void
    {
        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('private.key', 0));

        vfsStreamWrapper::setRoot($root);

        try {
            $this->box->signUsingFile('vfs://test/private.key');

            self::fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'The path "vfs://test/private.key" is not readable.',
                $exception->getMessage(),
            );
        }
    }

    private function getPrivateKey(): array
    {
        return [
            <<<'KEY_WRAP'
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
                KEY_WRAP,
            'test',
        ];
    }

    private function configureHelloWorldPhar(): void
    {
        $this->box->getPhar()->addFromString(
            'main.php',
            <<<'PHP_WRAP'
                <?php

                echo 'Hello, world!'.PHP_EOL;
                PHP_WRAP,
        );

        $this->box->getPhar()->setStub(
            StubGenerator::generateStub(
                index: 'main.php',
                checkRequirements: false,
            ),
        );
    }
}
