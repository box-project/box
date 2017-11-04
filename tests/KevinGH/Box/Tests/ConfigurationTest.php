<?php

namespace KevinGH\Box\Tests;

use Herrera\Annotations\Tokenizer;
use Herrera\PHPUnit\TestCase;
use KevinGH\Box\Configuration;
use Phar;
use SplFileInfo;

/**
 * @requires PHP 7.2.0
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

    public function testGetAlias()
    {
        $this->assertEquals('default.phar', $this->config->getAlias());
    }

    public function testGetAliasSet()
    {
        $this->setConfig(array('alias' => 'test.phar'));

        $this->assertEquals('test.phar', $this->config->getAlias());
    }

    public function testGetBasePath()
    {
        $this->assertEquals($this->dir, $this->config->getBasePath());
    }

    public function testGetBasePathSet()
    {
        mkdir($this->dir . DIRECTORY_SEPARATOR . 'test');

        $this->setConfig(
            array(
                'base-path' => $this->dir . DIRECTORY_SEPARATOR . 'test'
            )
        );

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'test',
            $this->config->getBasePath()
        );
    }

    public function testGetBasePathNotExist()
    {
        $this->setConfig(
            array(
                'base-path' => $this->dir . DIRECTORY_SEPARATOR . 'test'
            )
        );

        $this->setExpectedException(
            'InvalidArgumentException',
            'The base path "' . $this->dir . DIRECTORY_SEPARATOR . 'test" is not a directory or does not exist.'
        );

        $this->config->getBasePath();
    }

    /**
     * @depends testGetBasePath
     */
    public function testGetBasePathRegex()
    {
        $this->assertEquals(
            '/' . preg_quote($this->config->getBasePath() . DIRECTORY_SEPARATOR, '/') . '/',
            $this->config->getBasePathRegex()
        );
    }

    public function testGetBinaryDirectories()
    {
        $this->assertSame(array(), $this->config->getBinaryDirectories());
    }

    public function testGetBinaryDirectoriesSet()
    {
        mkdir($this->dir . DIRECTORY_SEPARATOR . 'test');

        $this->setConfig(array('directories-bin' => 'test'));

        $this->assertEquals(
            array($this->dir . DIRECTORY_SEPARATOR . 'test'),
            $this->config->getBinaryDirectories()
        );
    }

    public function testGetBinaryDirectoriesIterator()
    {
        $this->assertNull($this->config->getBinaryDirectoriesIterator());
    }

    public function testGetBinaryDirectoriesIteratorSet()
    {
        mkdir('alpha');
        touch('alpha/beta.png');
        touch('alpha/gamma.png');

        $this->setConfig(
            array(
                'blacklist' => 'alpha/beta.png',
                'directories-bin' => 'alpha'
            )
        );

        $iterator = $this->config
                         ->getBinaryDirectoriesIterator()
                         ->getIterator();

        foreach ($iterator as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('gamma.png', $file->getBasename());
        }
    }

    public function testGetBinaryFiles()
    {
        $this->assertSame(array(), $this->config->getBinaryFiles());
    }

    public function testGetBinaryFilesSet()
    {
        mkdir($this->dir . DIRECTORY_SEPARATOR . 'test');

        $this->setConfig(array('files-bin' => 'test.png'));

        foreach ($this->config->getBinaryFiles() as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('test.png', $file->getBasename());
        }
    }

    public function testGetBinaryFilesIterator()
    {
        $this->assertNull($this->config->getBinaryFilesIterator());
    }

    public function testGetBinaryFilesIteratorSet()
    {
        $this->setConfig(array('files-bin' => 'test.png'));

        foreach ($this->config->getBinaryFilesIterator() as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('test.png', $file->getBasename());
        }
    }

    public function testGetBinaryFinders()
    {
        $this->assertSame(array(), $this->config->getBinaryFinders());
    }

    public function testGetBinaryFindersSet()
    {
        touch('bad.jpg');
        touch('test.jpg');
        touch('test.png');
        touch('test.php');

        $this->setConfig(
            array(
                'blacklist' => array('bad.jpg'),
                'finder-bin' => array(
                    array(
                        'name' => '*.png',
                        'in' => '.'
                    ),
                    array(
                        'name' => '*.jpg',
                        'in' => '.'
                    )
                )
            )
        );

        /** @var $results \SplFileInfo[] */
        $results = array();
        $finders = $this->config->getBinaryFinders();

        foreach ($finders as $finder) {
            foreach ($finder as $result) {
                $results[] = $result;
            }
        }

        $this->assertEquals('test.png', $results[0]->getBasename());
        $this->assertEquals('test.jpg', $results[1]->getBasename());
    }

    public function testGetBlacklist()
    {
        $this->assertSame(array(), $this->config->getBlacklist());
    }

    public function testGetBlacklistSet()
    {
        $this->setConfig(array('blacklist' => array('test')));

        $this->assertEquals(array('test'), $this->config->getBlacklist());
    }

    public function testGetBlacklistFilter()
    {
        mkdir('sub');
        touch('alpha.php');
        touch('beta.php');
        touch('sub/beta.php');

        $alpha = new SplFileInfo('alpha.php');
        $beta = new SplFileInfo('beta.php');
        $sub = new SplFileInfo('sub/alpha.php');

        $this->setConfig(array('blacklist' => 'beta.php'));

        $callable = $this->config->getBlacklistFilter();

        $this->assertNull($callable($alpha));
        $this->assertFalse($callable($beta));
        $this->assertNull($callable($sub));
    }

    public function testGetBootstrapFile()
    {
        $this->assertNull($this->config->getBootstrapFile());
    }

    public function testGetBootstrapFileSet()
    {
        $this->setconfig(array('bootstrap' => 'test.php'));

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'test.php',
            $this->config->getBootstrapFile()
        );
    }

    public function testGetCompactors()
    {
        $this->assertSame(array(), $this->config->getCompactors());
    }

    public function testGetCompactorsSet()
    {
        $this->setConfig(
            array(
                'compactors' => array(
                    'Herrera\\Box\\Compactor\\Php',
                    __NAMESPACE__ . '\\TestCompactor'
                )
            )
        );

        $compactors = $this->config->getCompactors();

        $this->assertInstanceof(
            'Herrera\\Box\\Compactor\\Php',
            $compactors[0]
        );
        $this->assertInstanceof(
            __NAMESPACE__ . '\\TestCompactor',
            $compactors[1]
        );
    }

    public function testGetCompactorsNoSuchClass()
    {
        $this->setConfig(array('compactors' => array('NoSuchClass')));

        $this->setExpectedException(
            'InvalidArgumentException',
            'The compactor class "NoSuchClass" does not exist.'
        );

        $this->config->getCompactors();
    }

    public function testGetCompactorsInvalidClass()
    {
        $this->setConfig(
            array('compactors' => array(__NAMESPACE__ . '\\InvalidCompactor'))
        );

        $this->setExpectedException(
            'InvalidArgumentException',
            'The class "' . __NAMESPACE__ . '\\InvalidCompactor" is not a compactor class.'
        );

        $this->config->getCompactors();
    }

    public function testGetCompactorsAnnotations()
    {
        $this->setConfig(
            array(
                'annotations' => (object) array(
                    'ignore' => array(
                        'author'
                    )
                ),
                'compactors' => array(
                    'Herrera\\Box\\Compactor\\Php'
                )
            )
        );

        $compactors = $this->config->getCompactors();

        /** @var Tokenizer $tokenizer */
        $tokenizer = $this->getPropertyValue($compactors[0], 'tokenizer');

        $this->assertNotNull($tokenizer);

        $this->assertEquals(
            array('author'),
            $this->getPropertyValue($tokenizer, 'ignored')
        );
    }

    public function testGetCompressionAlgorithm()
    {
        $this->assertNull($this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmSet()
    {
        $this->setConfig(array('compression' => Phar::BZ2));

        $this->assertEquals(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmSetString()
    {
        $this->setConfig(array('compression' => 'BZ2'));

        $this->assertEquals(Phar::BZ2, $this->config->getCompressionAlgorithm());
    }

    public function testGetCompressionAlgorithmInvalidString()
    {
        $this->setConfig(array('compression' => 'INVALID'));

        $this->setExpectedException(
            'InvalidArgumentException',
            'The compression algorithm "INVALID" is not supported.'
        );

        $this->config->getCompressionAlgorithm();
    }

    public function testGetDirectories()
    {
        $this->assertSame(array(), $this->config->getDirectories());
    }

    public function testGetDirectoriesSet()
    {
        $this->setConfig(array('directories' => array('test')));

        $this->assertEquals(
            array($this->dir . DIRECTORY_SEPARATOR . 'test'),
            $this->config->getDirectories()
        );
    }

    public function testGetDirectoriesTrailingSlashRemoved()
    {
        $this->setConfig(
            array('directories' => array('dir/subdir1/', 'dir/subdir2/'))
        );

        $this->assertEquals(
            array(
                $this->dir . DIRECTORY_SEPARATOR . 'dir/subdir1',
                $this->dir . DIRECTORY_SEPARATOR . 'dir/subdir2',
            ),
            $this->config->getDirectories()
        );
    }

    public function testGetDirectoriesIterator()
    {
        $this->assertNull($this->config->getDirectoriesIterator());
    }

    public function testGetDirectoriesIteratorSet()
    {
        mkdir('alpha');
        touch('alpha/beta.php');
        touch('alpha/gamma.php');

        $this->setConfig(
            array(
                'blacklist' => 'alpha/beta.php',
                'directories' => 'alpha'
            )
        );

        $iterator = $this->config
                         ->getDirectoriesIterator()
                         ->getIterator();

        foreach ($iterator as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('gamma.php', $file->getBasename());
        }
    }

    public function testGetFileMode()
    {
        $this->assertNull($this->config->getFileMode());
    }

    public function testGetFileModeSet()
    {
        $this->setConfig(array('chmod' => '0755'));

        $this->assertEquals(0755, $this->config->getFileMode());
    }

    public function testGetFiles()
    {
        $this->assertSame(array(), $this->config->getFiles());
    }

    public function testGetFilesNotExist()
    {
        $this->setConfig(array('files' => array('test.php')));

        $this->setExpectedException(
            'RuntimeException',
            'The file "'
                . $this->dir . DIRECTORY_SEPARATOR
                . 'test.php" does not exist or is not a file.'
        );

        $this->config->getFiles();
    }

    public function testGetFilesSet()
    {
        touch('test.php');

        $this->setConfig(array('files' => array('test.php')));

        foreach ($this->config->getFiles() as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('test.php', $file->getBasename());
        }
    }

    public function testGetFilesIterator()
    {
        $this->assertNull($this->config->getFilesIterator());
    }

    public function testGetFilesIteratorSet()
    {
        touch('test.php');

        $this->setConfig(array('files' => 'test.php'));

        foreach ($this->config->getFilesIterator() as $file) {
            /** @var $file SplFileInfo */
            $this->assertEquals('test.php', $file->getBasename());
        }
    }

    public function testGetFinders()
    {
        $this->assertSame(array(), $this->config->getFinders());
    }

    public function testGetFindersSet()
    {
        touch('bad.php');
        touch('test.html');
        touch('test.txt');
        touch('test.php');

        $this->setConfig(
            array(
                'blacklist' => array('bad.php'),
                'finder' => array(
                    array(
                        'name' => '*.php',
                        'in' => '.'
                    ),
                    array(
                        'name' => '*.html',
                        'in' => '.'
                    )
                )
            )
        );

        /** @var $results \SplFileInfo[] */
        $results = array();
        $finders = $this->config->getFinders();

        foreach ($finders as $finder) {
            foreach ($finder as $result) {
                $results[] = $result;
            }
        }

        $this->assertEquals('test.php', $results[0]->getBasename());
        $this->assertEquals('test.html', $results[1]->getBasename());
    }

    public function testGetDatetimeNow()
    {
        $this->assertRegExp(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',
            $this->config->getDatetimeNow('Y-m-d H:i:s')
        );
    }

    public function testGetDatetimeNowFormatted()
    {
        $this->assertRegExp(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',
            $this->config->getDatetimeNow('Y-m-d')
        );
    }

    public function testGetDatetimeNowPlaceHolder()
    {
        $this->assertNull($this->config->getDatetimeNowPlaceHolder());

        $this->setConfig(array('datetime' => 'date_time'));

        $this->assertEquals(
            'date_time',
            $this->config->getDatetimeNowPlaceHolder()
        );

    }

    public function testGetDatetimeFormat()
    {
        $this->assertEquals('Y-m-d H:i:s', $this->config->getDatetimeFormat());

        $this->setConfig(array('datetime_format' => 'Y-m-d'));

        $this->assertEquals(
            'Y-m-d',
            $this->config->getDatetimeFormat()
        );

    }

    public function testGetGitHash()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
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

    public function testGetGitHashShort()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
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

    public function testGetGitHashPlaceholder()
    {
        $this->assertNull($this->config->getGitHashPlaceholder());
    }

    public function testGetGitHashPlaceholderSet()
    {
        $this->setConfig(array('git-commit' => 'git_commit'));

        $this->assertEquals(
            'git_commit',
            $this->config->getGitHashPlaceholder()
        );
    }

    public function testGetGitShortHashPlaceholder()
    {
        $this->assertNull($this->config->getGitShortHashPlaceholder());
    }

    public function testGetGitShortHashPlaceholderSet()
    {
        $this->setConfig(array('git-commit-short' => 'git_commit_short'));

        $this->assertEquals(
            'git_commit_short',
            $this->config->getGitShortHashPlaceholder()
        );
    }

    public function testGitTag()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->assertEquals('1.0.0', $this->config->getGitTag());

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetGitTagPlaceholder()
    {
        $this->assertNull($this->config->getGitTagPlaceholder());
    }

    public function testGetGitTagPlaceholderSet()
    {
        $this->setConfig(array('git-tag' => '@git-tag@'));

        $this->assertEquals(
            '@git-tag@',
            $this->config->getGitTagPlaceholder()
        );
    }

    public function testGetGitVersion()
    {
        $this->setExpectedException(
            'RuntimeException',
            'Not a git repository'
        );

        $this->config->getGitVersion();
    }

    public function testGitVersionTag()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->assertEquals('1.0.0', $this->config->getGitVersion());

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGitVersionCommit()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
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

    public function testGetVersionPlaceholder()
    {
        $this->assertNull($this->config->getGitVersionPlaceholder());
    }

    public function testGetVersionPlaceholderSet()
    {
        $this->setConfig(array('git-version' => 'git_version'));

        $this->assertEquals(
            'git_version',
            $this->config->getGitVersionPlaceholder()
        );
    }

    public function testGetMainScriptPath()
    {
        $this->assertNull($this->config->getMainScriptPath());
    }

    public function testGetMainScriptPathSet()
    {
        $this->setConfig(array('main' => 'test.php'));

        $this->assertEquals('test.php', $this->config->getMainScriptPath());
    }

    public function testGetMainScriptContents()
    {
        $this->assertNull($this->config->getMainScriptContents());
    }

    public function testGetMainScriptContentsSet()
    {
        file_put_contents('test.php', "#!/usr/bin/env php\ntest");

        $this->setConfig(array('main' => 'test.php'));

        $this->assertEquals('test', $this->config->getMainScriptContents());
    }

    public function testGetMainScriptContentsReadError()
    {
        $this->setConfig(array('main' => 'test.php'));

        $this->setExpectedException(
            'RuntimeException',
            'No such file'
        );

        $this->config->getMainScriptContents();
    }

    public function testGetMap()
    {
        $this->assertSame(array(), $this->config->getMap());
    }

    public function testGetMapSet()
    {
        $this->setConfig(
            array(
                'map' => array(
                    array('a' => 'b'),
                    array('_empty_' => 'c')
                )
            )
        );

        $this->assertEquals(
            array(
                array('a' => 'b'),
                array('' => 'c')
            ),
            $this->config->getMap()
        );
    }

    public function testGetMapper()
    {
        $this->setConfig(
            array(
                'map' => array(
                    array('first/test/path' => 'a'),
                    array('' => 'b/')
                )
            )
        );

        $ds = DIRECTORY_SEPARATOR;
        $mapper = $this->config->getMapper();

        $this->assertEquals(
            "a{$ds}sub{$ds}path{$ds}file.php",
            $mapper('first/test/path/sub/path/file.php')
        );

        $this->assertEquals(
            "b{$ds}second{$ds}test{$ds}path{$ds}sub{$ds}path{$ds}file.php",
            $mapper('second/test/path/sub/path/file.php')
        );
    }

    public function testGetMetadata()
    {
        $this->assertNull($this->config->getMetadata());
    }

    public function testGetMetadataSet()
    {
        $this->setConfig(array('metadata' => 123));

        $this->assertSame(123, $this->config->getMetadata());
    }

    public function testGetMimetypeMapping()
    {
        $this->assertSame(array(), $this->config->getMimetypeMapping());
    }

    public function testGetMimetypeMappingSet()
    {
        $mimetypes = array('phps' => Phar::PHPS);

        $this->setConfig(array('mimetypes' => $mimetypes));

        $this->assertEquals($mimetypes, $this->config->getMimetypeMapping());
    }

    public function testGetMungVariables()
    {
        $this->assertSame(array(), $this->config->getMungVariables());
    }

    public function testGetMungVariablesSet()
    {
        $mung = array('REQUEST_URI');

        $this->setConfig(array('mung' => $mung));

        $this->assertEquals($mung, $this->config->getMungVariables());
    }

    public function testGetNotFoundScriptPath()
    {
        $this->assertNull($this->config->getNotFoundScriptPath());
    }

    public function testGetNotFoundScriptPathSet()
    {
        $this->setConfig(array('not-found' => 'test.php'));

        $this->assertEquals('test.php', $this->config->getNotFoundScriptPath());
    }

    public function testGetOutputPath()
    {
        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'default.phar',
            $this->config->getOutputPath()
        );
    }

    public function testGetOutputPathSet()
    {
        $this->setConfig(array('output' => 'test.phar'));

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'test.phar',
            $this->config->getOutputPath()
        );
    }

    /**
     * @depends testGetOutputPathSet
     */
    public function testGetOutputPathGitVersion()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(array('output' => 'test-@git-version@.phar'));

        $this->assertEquals(
            $this->dir . DIRECTORY_SEPARATOR . 'test-1.0.0.phar',
            $this->config->getOutputPath()
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetPrivateKeyPassphrase()
    {
        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSet()
    {
        $this->setConfig(array('key-pass' => 'test'));

        $this->assertEquals('test', $this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPassphraseSetBoolean()
    {
        $this->setConfig(array('key-pass' => true));

        $this->assertNull($this->config->getPrivateKeyPassphrase());
    }

    public function testGetPrivateKeyPath()
    {
        $this->assertNull($this->config->getPrivateKeyPath());
    }

    public function testGetPrivateKeyPathSet()
    {
        $this->setConfig(array('key' => 'test.pem'));

        $this->assertEquals('test.pem', $this->config->getPrivateKeyPath());
    }

    public function testGetProcessedReplacements()
    {
        $this->assertSame(array(), $this->config->getProcessedReplacements());
    }

    public function testGetProcessedReplacementsSet()
    {
        touch('test');
        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email test@test.test');
        exec('git add test');
        exec('git commit -m "Adding test file."');
        exec('git tag 1.0.0');

        $this->setConfig(
            array(
                'git-commit' => 'git_commit',
                'git-commit-short' => 'git_commit_short',
                'git-tag' => 'git_tag',
                'git-version' => 'git_version',
                'replacements' => array('rand' => $rand = rand()),
                'datetime' => 'date_time',
                'datetime_format' => 'Y:m:d'
            )
        );

        $values = $this->config->getProcessedReplacements();

        $this->assertRegExp('/^[a-f0-9]{40}$/', $values['@git_commit@']);
        $this->assertRegExp('/^[a-f0-9]{7}$/', $values['@git_commit_short@']);
        $this->assertEquals('1.0.0', $values['@git_tag@']);
        $this->assertEquals('1.0.0', $values['@git_version@']);
        $this->assertEquals($rand, $values['@rand@']);
        $this->assertRegExp(
            '/^[0-9]{4}:[0-9]{2}:[0-9]{2}$/',
            $values['@date_time@']
        );

        // some process does not release the git files
        if ($this->isWindows()) {
            exec('rd /S /Q .git');
        }
    }

    public function testGetReplacementSigil()
    {
        $this->assertEquals('@', $this->config->getReplacementSigil());

        $this->setConfig(array('replacement-sigil' => '$'));

        $this->assertEquals('$', $this->config->getReplacementSigil());
    }

    public function testGetReplacements()
    {
        $this->assertSame(array(), $this->config->getReplacements());
    }

    public function testGetReplacementsSet()
    {
        $replacements = array('rand' => rand());

        $this->setConfig(array('replacements' => (object) $replacements));

        $this->assertEquals($replacements, $this->config->getReplacements());
    }

    public function testGetShebang()
    {
        $this->assertNull($this->config->getShebang());
    }

    public function testGetShebangSet()
    {
        $this->setConfig(array('shebang' => '#!/bin/php'));

        $this->assertEquals('#!/bin/php', $this->config->getShebang());
    }

    public function testGetShebangInvalid()
    {
        $this->setConfig(array('shebang' => '/bin/php'));

        $this->setExpectedException(
            'InvalidArgumentException',
            'The shebang line must start with "#!": /bin/php'
        );

        $this->config->getShebang();
    }

    public function testGetShebangBlank()
    {
        $this->setConfig(array('shebang' => ''));

        $this->assertEquals('', $this->config->getShebang());
    }

    public function testGetShebangFalse()
    {
        $this->setConfig(array('shebang' => false));

        $this->assertEquals('', $this->config->getShebang());
    }

    public function testGetSigningAlgorithm()
    {
        $this->assertSame(Phar::SHA1, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSet()
    {
        $this->setConfig(array('algorithm' => Phar::MD5));

        $this->assertEquals(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmSetString()
    {
        $this->setConfig(array('algorithm' => 'MD5'));

        $this->assertEquals(Phar::MD5, $this->config->getSigningAlgorithm());
    }

    public function testGetSigningAlgorithmInvalidString()
    {
        $this->setConfig(array('algorithm' => 'INVALID'));

        $this->setExpectedException(
            'InvalidArgumentException',
            'The signing algorithm "INVALID" is not supported.'
        );

        $this->config->getSigningAlgorithm();
    }

    public function testGetStubBanner()
    {
        $this->assertNull($this->config->getStubBanner());
    }

    public function testGetStubBannerSet()
    {
        $comment = <<<COMMENT
This is a

multiline

comment.
COMMENT;

        $this->setConfig(array('banner' => $comment));

        $this->assertEquals($comment, $this->config->getStubBanner());
    }

    public function testGetStubBannerFromFile()
    {
        $this->assertNull($this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileSet()
    {
        $comment = <<<COMMENT
This is a

multiline

comment.
COMMENT;

        file_put_contents('banner', $comment);

        $this->setConfig(array('banner-file' => 'banner'));

        $this->assertEquals($comment, $this->config->getStubBannerFromFile());
    }

    public function testGetStubBannerFromFileReadError()
    {
        $this->setConfig(array('banner-file' => '/does/not/exist'));

        $this->setExpectedException(
            'RuntimeException',
            'No such file or directory'
        );

        $this->config->getStubBannerFromFile();
    }

    public function testGetStubBannerPath()
    {
        $this->assertNull($this->config->getStubBannerPath());
    }

    public function testGetStubBannerPathSet()
    {
        $this->setConfig(array('banner-file' => '/path/to/file'));

        $this->assertEquals(
            '/path/to/file',
            $this->config->getStubBannerPath()
        );
    }

    public function testGetStubPath()
    {
        $this->assertNull($this->config->getStubPath());
    }

    public function testGetStubPathSet()
    {
        $this->setConfig(array('stub' => 'test.php'));

        $this->assertEquals('test.php', $this->config->getStubPath());
    }

    public function testGetStubPathSetBoolean()
    {
        $this->setConfig(array('stub' => true));

        $this->assertNull($this->config->getStubPath());
    }

    public function testIsExtractable()
    {
        $this->assertFalse($this->config->isExtractable());
    }

    public function testIsExtractableSet()
    {
        $this->setConfig(array('extract' => true));

        $this->assertTrue($this->config->isExtractable());
    }

    public function testIsInterceptFileFuncs()
    {
        $this->assertFalse($this->config->isInterceptFileFuncs());
    }

    public function testIsInterceptFileFuncsSet()
    {
        $this->setConfig(array('intercept' => true));

        $this->assertTrue($this->config->isInterceptFileFuncs());
    }

    public function testIsPrivateKeyPrompt()
    {
        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSet()
    {
        $this->setConfig(array('key-pass' => true));

        $this->assertTrue($this->config->isPrivateKeyPrompt());
    }

    public function testIsPrivateKeyPromptSetString()
    {
        $this->setConfig(array('key-pass' => 'test'));

        $this->assertFalse($this->config->isPrivateKeyPrompt());
    }

    public function testIsStubGenerated()
    {
        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSet()
    {
        $this->setConfig(array('stub' => true));

        $this->assertTrue($this->config->isStubGenerated());
    }

    public function testIsStubGeneratedSetString()
    {
        $this->setConfig(array('stub' => 'test.php'));

        $this->assertFalse($this->config->isStubGenerated());
    }

    public function testIsWebPhar()
    {
        $this->assertFalse($this->config->isWebPhar());
    }

    public function testIsWebPharSet()
    {
        $this->setConfig(array('web' => true));

        $this->assertTrue($this->config->isWebPhar());
    }

    public function testLoadBootstrap()
    {
        file_put_contents(
            'test.php',
            <<<CODE
<?php define('TEST_BOOTSTRAP_FILE_LOADED', true);
CODE
        );

        $this->setConfig(array('bootstrap' => 'test.php'));

        $this->config->loadBootstrap();

        $this->assertTrue(defined('TEST_BOOTSTRAP_FILE_LOADED'));
    }

    public function testLoadBootstrapNotExist()
    {
        $this->setConfig(array('bootstrap' => 'test.php'));

        $this->setExpectedException(
            'InvalidArgumentException',
            'The bootstrap path "' . $this->dir . DIRECTORY_SEPARATOR . 'test.php" is not a file or does not exist.'
        );

        $this->config->loadBootstrap();
    }

    public function testProcessFindersInvalidMethod()
    {
        $this->setConfig(
            array('finder' => array(array('invalidMethod' => 'whargarbl')))
        );

        $this->setExpectedException(
            'InvalidArgumentException',
            'The method "Finder::invalidMethod" does not exist.'
        );

        $this->config->getFinders();
    }

    protected function tearDown()
    {
        chdir($this->cwd);

        parent::tearDown();
    }

    protected function setUp()
    {
        $this->cwd = getcwd();
        $this->dir = $this->createDir();
        $this->file = $this->dir . DIRECTORY_SEPARATOR . 'box.json';
        $this->config = new Configuration($this->file, (object) array());

        chdir($this->dir);
        touch($this->file);
    }

    private function setConfig(array $config)
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
