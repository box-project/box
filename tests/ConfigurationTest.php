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

use Herrera\Annotations\Tokenizer;
use Herrera\PHPUnit\TestCase;
use Phar;
use SplFileInfo;

/**
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $config;

    private $cwd;
    private $dir;
    private $file;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->dir = $this->createDir();
        $this->file = $this->dir.DIRECTORY_SEPARATOR.'box.json';
        $this->config = new Configuration($this->file, (object) []);

        chdir($this->dir);
        touch($this->file);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);

        parent::tearDown();
    }

    public function testGetAlias(): void
    {
        $this->assertSame('default.phar', $this->config->getAlias());
    }

    public function testGetAliasSet(): void
    {
        $this->setConfig(['alias' => 'test.phar']);

        $this->assertSame('test.phar', $this->config->getAlias());
    }

    public function testGetBasePath(): void
    {
        $this->assertSame($this->dir, $this->config->getBasePath());
    }

    public function testGetBasePathSet(): void
    {
        mkdir($this->dir.DIRECTORY_SEPARATOR.'test');

        $this->setConfig(
            [
                'base-path' => $this->dir.DIRECTORY_SEPARATOR.'test',
            ]
        );

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'test',
            $this->config->getBasePath()
        );
    }

    public function testGetBasePathNotExist(): void
    {
        $this->setConfig(
            [
                'base-path' => $this->dir.DIRECTORY_SEPARATOR.'test',
            ]
        );

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The base path "'.$this->dir.DIRECTORY_SEPARATOR.'test" is not a directory or does not exist.'
        );

        $this->config->getBasePath();
    }

    /**
     * @depends testGetBasePath
     */
    public function testGetBasePathRegex(): void
    {
        $this->assertSame(
            '/'.preg_quote($this->config->getBasePath().DIRECTORY_SEPARATOR, '/').'/',
            $this->config->getBasePathRegex()
        );
    }

    public function testGetBinaryDirectories(): void
    {
        $this->assertSame([], $this->config->getBinaryDirectories());
    }

    public function testGetBinaryDirectoriesSet(): void
    {
        mkdir($this->dir.DIRECTORY_SEPARATOR.'test');

        $this->setConfig(['directories-bin' => 'test']);

        $this->assertSame(
            [$this->dir.DIRECTORY_SEPARATOR.'test'],
            $this->config->getBinaryDirectories()
        );
    }

    public function testGetBinaryDirectoriesIterator(): void
    {
        $this->assertNull($this->config->getBinaryDirectoriesIterator());
    }

    public function testGetBinaryDirectoriesIteratorSet(): void
    {
        mkdir('alpha');
        touch('alpha/beta.png');
        touch('alpha/gamma.png');

        $this->setConfig(
            [
                'blacklist' => 'alpha/beta.png',
                'directories-bin' => 'alpha',
            ]
        );

        $iterator = $this->config
                         ->getBinaryDirectoriesIterator()
                         ->getIterator();

        foreach ($iterator as $file) {
            // @var $file SplFileInfo
            $this->assertSame('gamma.png', $file->getBasename());
        }
    }

    public function testGetBinaryFiles(): void
    {
        $this->assertSame([], $this->config->getBinaryFiles());
    }

    public function testGetBinaryFilesSet(): void
    {
        mkdir($this->dir.DIRECTORY_SEPARATOR.'test');

        $this->setConfig(['files-bin' => 'test.png']);

        foreach ($this->config->getBinaryFiles() as $file) {
            // @var $file SplFileInfo
            $this->assertSame('test.png', $file->getBasename());
        }
    }

    public function testGetBinaryFilesIterator(): void
    {
        $this->assertNull($this->config->getBinaryFilesIterator());
    }

    public function testGetBinaryFilesIteratorSet(): void
    {
        $this->setConfig(['files-bin' => 'test.png']);

        foreach ($this->config->getBinaryFilesIterator() as $file) {
            // @var $file SplFileInfo
            $this->assertSame('test.png', $file->getBasename());
        }
    }

    public function testGetBinaryFinders(): void
    {
        $this->assertSame([], $this->config->getBinaryFinders());
    }

    public function testGetBinaryFindersSet(): void
    {
        touch('bad.jpg');
        touch('test.jpg');
        touch('test.png');
        touch('test.php');

        $this->setConfig(
            [
                'blacklist' => ['bad.jpg'],
                'finder-bin' => [
                    [
                        'name' => '*.png',
                        'in' => '.',
                    ],
                    [
                        'name' => '*.jpg',
                        'in' => '.',
                    ],
                ],
            ]
        );

        /** @var $results \SplFileInfo[] */
        $results = [];
        $finders = $this->config->getBinaryFinders();

        foreach ($finders as $finder) {
            foreach ($finder as $result) {
                $results[] = $result;
            }
        }

        $this->assertSame('test.png', $results[0]->getBasename());
        $this->assertSame('test.jpg', $results[1]->getBasename());
    }

    public function testGetBlacklist(): void
    {
        $this->assertSame([], $this->config->getBlacklist());
    }

    public function testGetBlacklistSet(): void
    {
        $this->setConfig(['blacklist' => ['test']]);

        $this->assertSame(['test'], $this->config->getBlacklist());
    }

    public function testGetBlacklistFilter(): void
    {
        mkdir('sub');
        touch('alpha.php');
        touch('beta.php');
        touch('sub/beta.php');

        $alpha = new SplFileInfo('alpha.php');
        $beta = new SplFileInfo('beta.php');
        $sub = new SplFileInfo('sub/alpha.php');

        $this->setConfig(['blacklist' => 'beta.php']);

        $callable = $this->config->getBlacklistFilter();

        $this->assertNull($callable($alpha));
        $this->assertFalse($callable($beta));
        $this->assertNull($callable($sub));
    }

    public function testGetBootstrapFile(): void
    {
        $this->assertNull($this->config->getBootstrapFile());
    }

    public function testGetBootstrapFileSet(): void
    {
        $this->setconfig(['bootstrap' => 'test.php']);

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'test.php',
            $this->config->getBootstrapFile()
        );
    }

    public function testGetCompactors(): void
    {
        $this->assertSame([], $this->config->getCompactors());
    }

    public function testGetCompactorsSet(): void
    {
        $this->setConfig(
            [
                'compactors' => [
                    'Herrera\\Box\\Compactor\\Php',
                    __NAMESPACE__.'\\TestCompactor',
                ],
            ]
        );

        $compactors = $this->config->getCompactors();

        $this->assertInstanceof(
            'Herrera\\Box\\Compactor\\Php',
            $compactors[0]
        );
        $this->assertInstanceof(
            __NAMESPACE__.'\\TestCompactor',
            $compactors[1]
        );
    }

    public function testGetCompactorsNoSuchClass(): void
    {
        $this->setConfig(['compactors' => ['NoSuchClass']]);

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The compactor class "NoSuchClass" does not exist.'
        );

        $this->config->getCompactors();
    }

    public function testGetCompactorsInvalidClass(): void
    {
        $this->setConfig(
            ['compactors' => [__NAMESPACE__.'\\InvalidCompactor']]
        );

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The class "'.__NAMESPACE__.'\\InvalidCompactor" is not a compactor class.'
        );

        $this->config->getCompactors();
    }

    public function testGetCompactorsAnnotations(): void
    {
        $this->setConfig(
            [
                'annotations' => (object) [
                    'ignore' => [
                        'author',
                    ],
                ],
                'compactors' => [
                    'Herrera\\Box\\Compactor\\Php',
                ],
            ]
        );

        $compactors = $this->config->getCompactors();

        /** @var Tokenizer $tokenizer */
        $tokenizer = $this->getPropertyValue($compactors[0], 'tokenizer');

        $this->assertNotNull($tokenizer);

        $this->assertSame(
            ['author'],
            $this->getPropertyValue($tokenizer, 'ignored')
        );
    }

    public function testGetCompressionAlgorithm(): void
    {
        $this->assertNull($this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmSet(): void
    {
        $this->setConfig(['compression' => Phar::BZ2]);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmSetString(): void
    {
        $this->setConfig(['compression' => 'BZ2']);

        $this->assertSame(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmInvalidString(): void
    {
        $this->setConfig(['compression' => 'INVALID']);

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The compression algorithm "INVALID" is not supported.'
        );

        $this->config->getCompressionAlgorithm();
    }

    public function testGetDirectories(): void
    {
        $this->assertSame([], $this->config->getDirectories());
    }

    public function testGetDirectoriesSet(): void
    {
        $this->setConfig(['directories' => ['test']]);

        $this->assertSame(
            [$this->dir.DIRECTORY_SEPARATOR.'test'],
            $this->config->getDirectories()
        );
    }

    public function testGetDirectoriesTrailingSlashRemoved(): void
    {
        $this->setConfig(
            ['directories' => ['dir/subdir1/', 'dir/subdir2/']]
        );

        $this->assertSame(
            [
                $this->dir.DIRECTORY_SEPARATOR.'dir/subdir1',
                $this->dir.DIRECTORY_SEPARATOR.'dir/subdir2',
            ],
            $this->config->getDirectories()
        );
    }

    public function testGetDirectoriesIterator(): void
    {
        $this->assertNull($this->config->getDirectoriesIterator());
    }

    public function testGetDirectoriesIteratorSet(): void
    {
        mkdir('alpha');
        touch('alpha/beta.php');
        touch('alpha/gamma.php');

        $this->setConfig(
            [
                'blacklist' => 'alpha/beta.php',
                'directories' => 'alpha',
            ]
        );

        $iterator = $this->config
                         ->getDirectoriesIterator()
                         ->getIterator();

        foreach ($iterator as $file) {
            // @var $file SplFileInfo
            $this->assertSame('gamma.php', $file->getBasename());
        }
    }

    public function testGetFileMode(): void
    {
        $this->assertNull($this->config->getFileMode());
    }

    public function testGetFileModeSet(): void
    {
        $this->setConfig(['chmod' => '0755']);

        $this->assertSame(0755, $this->config->getFileMode());
    }

    public function testGetFiles(): void
    {
        $this->assertSame([], $this->config->getFiles());
    }

    public function testGetFilesNotExist(): void
    {
        $this->setConfig(['files' => ['test.php']]);

        $this->expectException(
            'RuntimeException'
        );
        $this->expectExceptionMessage(
            'The file "'
                .$this->dir.DIRECTORY_SEPARATOR
                .'test.php" does not exist or is not a file.'
        );

        $this->config->getFiles();
    }

    public function testGetFilesSet(): void
    {
        touch('test.php');

        $this->setConfig(['files' => ['test.php']]);

        foreach ($this->config->getFiles() as $file) {
            // @var $file SplFileInfo
            $this->assertSame('test.php', $file->getBasename());
        }
    }

    public function testGetFilesIterator(): void
    {
        $this->assertNull($this->config->getFilesIterator());
    }

    public function testGetFilesIteratorSet(): void
    {
        touch('test.php');

        $this->setConfig(['files' => 'test.php']);

        foreach ($this->config->getFilesIterator() as $file) {
            // @var $file SplFileInfo
            $this->assertSame('test.php', $file->getBasename());
        }
    }

    public function testGetFinders(): void
    {
        $this->assertSame([], $this->config->getFinders());
    }

    public function testGetFindersSet(): void
    {
        touch('bad.php');
        touch('test.html');
        touch('test.txt');
        touch('test.php');

        $this->setConfig(
            [
                'blacklist' => ['bad.php'],
                'finder' => [
                    [
                        'name' => '*.php',
                        'in' => '.',
                    ],
                    [
                        'name' => '*.html',
                        'in' => '.',
                    ],
                ],
            ]
        );

        /** @var $results \SplFileInfo[] */
        $results = [];
        $finders = $this->config->getFinders();

        foreach ($finders as $finder) {
            foreach ($finder as $result) {
                $results[] = $result;
            }
        }

        $this->assertSame('test.php', $results[0]->getBasename());
        $this->assertSame('test.html', $results[1]->getBasename());
    }

    public function testGetDatetimeNow(): void
    {
        $this->assertRegExp(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',
            $this->config->getDatetimeNow('Y-m-d H:i:s')
        );
    }

    public function testGetDatetimeNowFormatted(): void
    {
        $this->assertRegExp(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',
            $this->config->getDatetimeNow('Y-m-d')
        );
    }

    public function testGetDatetimeNowPlaceHolder(): void
    {
        $this->assertNull($this->config->getDatetimeNowPlaceHolder());

        $this->setConfig(['datetime' => 'date_time']);

        $this->assertSame(
            'date_time',
            $this->config->getDatetimeNowPlaceHolder()
        );
    }

    public function testGetDatetimeFormat(): void
    {
        $this->assertSame('Y-m-d H:i:s', $this->config->getDatetimeFormat());

        $this->setConfig(['datetime_format' => 'Y-m-d']);

        $this->assertSame(
            'Y-m-d',
            $this->config->getDatetimeFormat()
        );
    }

    public function testGetGitHash(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');

        $this->assertRegExp(
            '/^[a-f0-9]{40}$/',
            $this->config->getGitHash()
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetGitHashShort(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');

        $this->assertRegExp(
            '/^[a-f0-9]{7}$/',
            $this->config->getGitHash(true)
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetGitHashPlaceholder(): void
    {
        $this->assertNull($this->config->getGitHashPlaceholder());
    }

    public function testGetGitHashPlaceholderSet(): void
    {
        $this->setConfig(['git-commit' => 'git_commit']);

        $this->assertSame(
            'git_commit',
            $this->config->getGitHashPlaceholder()
        );
    }

    public function testGetGitShortHashPlaceholder(): void
    {
        $this->assertNull($this->config->getGitShortHashPlaceholder());
    }

    public function testGetGitShortHashPlaceholderSet(): void
    {
        $this->setConfig(['git-commit-short' => 'git_commit_short']);

        $this->assertSame(
            'git_commit_short',
            $this->config->getGitShortHashPlaceholder()
        );
    }

    public function testGitTag(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->assertSame('1.0.0', $this->config->getGitTag());

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetGitTagPlaceholder(): void
    {
        $this->assertNull($this->config->getGitTagPlaceholder());
    }

    public function testGetGitTagPlaceholderSet(): void
    {
        $this->setConfig(['git-tag' => '@git-tag@']);

        $this->assertSame(
            '@git-tag@',
            $this->config->getGitTagPlaceholder()
        );
    }

    public function testGetGitVersion(): void
    {
        $this->expectException(
            'RuntimeException'
        );
        $this->expectExceptionMessage(
            'Not a git repository'
        );

        $this->config->getGitVersion();
    }

    public function testGitVersionTag(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->assertSame('1.0.0', $this->config->getGitVersion());

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGitVersionCommit(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');

        $this->assertRegExp(
            '/^[a-f0-9]{7}$/',
            $this->config->getGitVersion()
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetVersionPlaceholder(): void
    {
        $this->assertNull($this->config->getGitVersionPlaceholder());
    }

    public function testGetVersionPlaceholderSet(): void
    {
        $this->setConfig(['git-version' => 'git_version']);

        $this->assertSame(
            'git_version',
            $this->config->getGitVersionPlaceholder()
        );
    }

    public function testGetMainScriptPath(): void
    {
        $this->assertNull($this->config->getMainScriptPath());
    }

    public function testGetMainScriptPathSet(): void
    {
        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test.php', $this->config->getMainScriptPath());
    }

    public function testGetMainScriptContents(): void
    {
        $this->assertNull($this->config->getMainScriptContents());
    }

    public function testGetMainScriptContentsSet(): void
    {
        file_put_contents('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(['main' => 'test.php']);

        $this->assertSame('test', $this->config->getMainScriptContents());
    }

    public function testGetMainScriptContentsReadError(): void
    {
        $this->setConfig(['main' => 'test.php']);

        $this->expectException(
            'RuntimeException'
        );
        $this->expectExceptionMessage(
            'No such file'
        );

        $this->config->getMainScriptContents();
    }

    public function testGetMap(): void
    {
        $this->assertSame([], $this->config->getMap());
    }

    public function testGetMapSet(): void
    {
        $this->setConfig(
            [
                'map' => [
                    ['a' => 'b'],
                    ['_empty_' => 'c'],
                ],
            ]
        );

        $this->assertSame(
            [
                ['a' => 'b'],
                ['' => 'c'],
            ],
            $this->config->getMap()
        );
    }

    public function testGetMapper(): void
    {
        $this->setConfig(
            [
                'map' => [
                    ['first/test/path' => 'a'],
                    ['' => 'b/'],
                ],
            ]
        );

        $ds = DIRECTORY_SEPARATOR;
        $mapper = $this->config->getMapper();

        $this->assertSame(
            "a{$ds}sub{$ds}path{$ds}file.php",
            $mapper('first/test/path/sub/path/file.php')
        );

        $this->assertSame(
            "b{$ds}second{$ds}test{$ds}path{$ds}sub{$ds}path{$ds}file.php",
            $mapper('second/test/path/sub/path/file.php')
        );
    }

    public function testGetMetadata(): void
    {
        $this->assertNull($this->config->getMetadata());
    }

    public function testGetMetadataSet(): void
    {
        $this->setConfig(['metadata' => 123]);

        $this->assertSame(123, $this->config->getMetadata());
    }

    public function testGetMimetypeMapping(): void
    {
        $this->assertSame([], $this->config->getMimetypeMapping());
    }

    public function testGetMimetypeMappingSet(): void
    {
        $mimetypes = ['phps' => Phar::PHPS];

        $this->setConfig(['mimetypes' => $mimetypes]);

        $this->assertSame($mimetypes, $this->config->getMimetypeMapping());
    }

    public function testGetMungVariables(): void
    {
        $this->assertSame([], $this->config->getMungVariables());
    }

    public function testGetMungVariablesSet(): void
    {
        $mung = ['REQUEST_URI'];

        $this->setConfig(['mung' => $mung]);

        $this->assertSame($mung, $this->config->getMungVariables());
    }

    public function testGetNotFoundScriptPath(): void
    {
        $this->assertNull($this->config->getNotFoundScriptPath());
    }

    public function testGetNotFoundScriptPathSet(): void
    {
        $this->setConfig(['not-found' => 'test.php']);

        $this->assertSame('test.php', $this->config->getNotFoundScriptPath());
    }

    public function testGetOutputPath(): void
    {
        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'default.phar',
            $this->config->getOutputPath()
        );
    }

    public function testGetOutputPathSet(): void
    {
        $this->setConfig(['output' => 'test.phar']);

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'test.phar',
            $this->config->getOutputPath()
        );
    }

    /**
     * @depends testGetOutputPathSet
     */
    public function testGetOutputPathGitVersion(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(['output' => 'test-@git-version@.phar']);

        $this->assertSame(
            $this->dir.DIRECTORY_SEPARATOR.'test-1.0.0.phar',
            $this->config->getOutputPath()
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetPrivateKeyPassphrase(): void
    {
        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSet(): void
    {
        $this->setConfig(['key-pass' => 'test']);

        $this->assertSame('test', $this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSetBoolean(): void
    {
        $this->setConfig(['key-pass' => true]);

        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPath(): void
    {
        $this->assertNull($this->config->getPrivateKeyPath());
    }

    public function testGetPrivateKeyPathSet(): void
    {
        $this->setConfig(['key' => 'test.pem']);

        $this->assertSame('test.pem', $this->config->getPrivateKeyPath());
    }

    public function testGetProcessedReplacements(): void
    {
        $this->assertSame([], $this->config->getProcessedReplacements());
    }

    public function testGetProcessedReplacementsSet(): void
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git config commit.gpgsign false');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(
            [
                'git-commit' => 'git_commit',
                'git-commit-short' => 'git_commit_short',
                'git-tag' => 'git_tag',
                'git-version' => 'git_version',
                'replacements' => ['rand' => $rand = random_int(0, getrandmax())],
                'datetime' => 'date_time',
                'datetime_format' => 'Y:m:d',
            ]
        );

        $values = $this->config->getProcessedReplacements();

        $this->assertRegExp('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertRegExp('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertSame('1.0.0', $values['@git_tag@']);
        $this->assertSame('1.0.0', $values['@git_version@']);
        $this->assertSame($rand, $values['@rand@']);
        $this->assertRegExp(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['@date_time@']
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetReplacementSigil(): void
    {
        $this->assertSame('@', $this->config->getReplacementSigil());

        $this->setConfig(['replacement-sigil' => '$']);

        $this->assertSame('$', $this->config->getReplacementSigil());
    }

    public function testGetReplacements(): void
    {
        $this->assertSame([], $this->config->getReplacements());
    }

    public function testGetReplacementsSet(): void
    {
        $replacements = ['rand' => random_int(0, getrandmax())];

        $this->setConfig(['replacements' => (object) $replacements]);

        $this->assertSame($replacements, $this->config->getReplacements());
    }

    public function testGetShebang(): void
    {
        $this->assertNull($this->config->getShebang());
    }

    public function testGetShebangSet(): void
    {
        $this->setConfig(['shebang' => '#!/bin/php']);

        $this->assertSame('#!/bin/php', $this->config->getShebang());
    }

    public function testGetShebangInvalid(): void
    {
        $this->setConfig(['shebang' => '/bin/php']);

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The shebang line must start with "#!": /bin/php'
        );

        $this->config->getShebang();
    }

    public function testGetShebangBlank(): void
    {
        $this->setConfig(['shebang' => '']);

        $this->assertSame('', $this->config->getShebang());
    }

    public function testGetShebangFalse(): void
    {
        $this->setConfig(['shebang' => false]);

        $this->assertSame('', $this->config->getShebang());
    }

    public function testGetSigningAlgorithm(): void
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSet(): void
    {
        $this->setConfig(['algorithm' => Phar::MD5]);

        $this->assertSame(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSetString(): void
    {
        $this->setConfig(['algorithm' => 'MD5']);

        $this->assertSame(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmInvalidString(): void
    {
        $this->setConfig(['algorithm' => 'INVALID']);

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The signing algorithm "INVALID" is not supported.'
        );

        $this->config->getSigningAlgorithm();
    }

    public function testGetStubBanner(): void
    {
        $this->assertNull($this->config->getStubBanner());
    }

    public function testGetStubBannerSet(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        $this->setConfig(['banner' => $comment]);

        $this->assertSame($comment, $this->config->getStubBanner());
    }

    public function testGetStubBannerFromFile(): void
    {
        $this->assertNull($this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileSet(): void
    {
        $comment = <<<'COMMENT'
This is a

multiline

comment.
COMMENT;

        file_put_contents('banner', $comment);

        $this->setConfig(['banner-file' => 'banner']);

        $this->assertSame($comment, $this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileReadError(): void
    {
        $this->setConfig(['banner-file' => '/does/not/exist']);

        $this->expectException(
            'RuntimeException'
        );
        $this->expectExceptionMessage(
            'No such file or directory'
        );

        $this->config->getStubBannerFromFile();
    }

    public function testGetStubBannerPath(): void
    {
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function testGetStubBannerPathSet(): void
    {
        $this->setConfig(['banner-file' => '/path/to/file']);

        $this->assertSame(
            '/path/to/file',
            $this->config->getStubBannerPath()
        );
    }

    public function testGetStubPath(): void
    {
        $this->assertNull($this->config->getStubPath());
    }

    public function testGetStubPathSet(): void
    {
        $this->setConfig(['stub' => 'test.php']);

        $this->assertSame('test.php', $this->config->getStubPath());
    }

    public function testGetStubPathSetBoolean(): void
    {
        $this->setConfig(['stub' => true]);

        $this->assertNull($this->config->getStubPath());
    }

    public function testIsExtractable(): void
    {
        $this->assertFalse($this->config->isExtractable());
    }

    public function testIsExtractableSet(): void
    {
        $this->setConfig(['extract' => true]);

        $this->assertTrue($this->config->isExtractable());
    }

    public function testIsInterceptFileFuncs(): void
    {
        $this->assertFalse($this->config->isInterceptFileFuncs());
    }

    public function testIsInterceptFileFuncsSet(): void
    {
        $this->setConfig(['intercept' => true]);

        $this->assertTrue($this->config->isInterceptFileFuncs());
    }

    public function testIsPrivateKeyPrompt(): void
    {
        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSet(): void
    {
        $this->setConfig(['key-pass' => true]);

        $this->assertTrue($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSetString(): void
    {
        $this->setConfig(['key-pass' => 'test']);

        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsStubGenerated(): void
    {
        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSet(): void
    {
        $this->setConfig(['stub' => true]);

        $this->assertTrue($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSetString(): void
    {
        $this->setConfig(['stub' => 'test.php']);

        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsWebPhar(): void
    {
        $this->assertFalse($this->config->isWebPhar());
    }

    public function testIsWebPharSet(): void
    {
        $this->setConfig(['web' => true]);

        $this->assertTrue($this->config->isWebPhar());
    }

    public function testLoadBootstrap(): void
    {
        file_put_contents(
            'test.php',
            <<<'CODE'
<?php define('TEST_BOOTSTRAP_FILE_LOADED', true);
CODE
        );

        $this->setConfig(['bootstrap' => 'test.php']);

        $this->config->loadBootstrap();

        $this->assertTrue(defined('TEST_BOOTSTRAP_FILE_LOADED'));
    }

    public function testLoadBootstrapNotExist(): void
    {
        $this->setConfig(['bootstrap' => 'test.php']);

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The bootstrap path "'.$this->dir.DIRECTORY_SEPARATOR.'test.php" is not a file or does not exist.'
        );

        $this->config->loadBootstrap();
    }

    public function testProcessFindersInvalidMethod(): void
    {
        $this->setConfig(
            ['finder' => [['invalidMethod' => 'whargarbl']]]
        );

        $this->expectException(
            'InvalidArgumentException'
        );
        $this->expectExceptionMessage(
            'The method "Finder::invalidMethod" does not exist.'
        );

        $this->config->getFinders();
    }

    private function setConfig(array $config): void
    {
        $this->setPropertyValue($this->config, 'raw', (object) $config);
    }

    private function isWindows()
    {
        if (false === strpos(strtolower(PHP_OS), 'darwin') && false !== strpos(strtolower(PHP_OS), 'win')) {
            return true;
        }

        return false;
    }
}
