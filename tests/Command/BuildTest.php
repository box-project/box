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

namespace KevinGH\Box\Command;

use DirectoryIterator;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use PharFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;

/**
 * @covers \KevinGH\Box\Command\Build
 */
class BuildTest extends CommandTestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/build';

    public function test_it_can_provide_a_comprehensive_help()
    {
        $commandTester = new CommandTester(
            $this->application->get('help')
        );

        $commandTester->execute(
            [
                'command_name' => 'build',
            ],
            ['interactive' => false]
        );

        $expected = <<<'OUTPUT'
Usage:
  build [options]

Options:
  -c, --configuration=CONFIGURATION  The alternative configuration file path.
  -h, --help                         Display this help message
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi                         Force ANSI output
      --no-ansi                      Disable ANSI output
  -n, --no-interaction               Do not ask any interactive question
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  The build command will build a new PHAR based on a variety of settings.

    This command relies on a configuration file for loading
    PHAR packaging settings. If a configuration file is not
    specified through the --configuration|-c option, one of
    the following files will be used (in order): box.json,
    box.json.dist

  The configuration file is actually a JSON object saved to a file.
  Note that all settings are optional.

    {
      "algorithm": ?,
      "alias": ?,
      "banner": ?,
      "banner-file": ?,
      "base-path": ?,
      "blacklist": ?,
      "bootstrap": ?,
      "chmod": ?,
      "compactors": ?,
      "compression": ?,
      "datetime": ?,
      "datetime_format": ?,
      "directories": ?,
      "directories-bin": ?,
      "extract": ?,
      "files": ?,
      "files-bin": ?,
      "finder": ?,
      "finder-bin": ?,
      "git-version": ?,
      "intercept": ?,
      "key": ?,
      "key-pass": ?,
      "main": ?,
      "map": ?,
      "metadata": ?,
      "mimetypes": ?,
      "mung": ?,
      "not-found": ?,
      "output": ?,
      "replacements": ?,
      "shebang": ?,
      "stub": ?,
      "web": ?
    }


  The (optional) algorithm (string) setting is the signing algorithm to use when
  the PHAR is built (Phar::setSignatureAlgorithm()). The following is a list of
  the signature algorithms available:

    - MD5 (Phar::MD5)
    - SHA1 (Phar::SHA1)
    - SHA256 (Phar::SHA256)
    - SHA512 (Phar::SHA512)
    - OPENSSL (Phar::OPENSSL)

  Further help:

    https://secure.php.net/manual/en/phar.setsignaturealgorithm.php


  The alias (string) setting is used when generating a new stub to call the
  Phar::mapPhar() method if the PHAR is for the CLI and the method
  Phar::webPhar() if the PHAR is configured for the web. This makes it easier to
  refer to files in the PHAR.

  Further help:

    - https://secure.php.net/manual/en/phar.mapphar.php
    - https://secure.php.net/manual/en/phar.webphar.php


  The annotations (boolean, object) setting is used to enable compacting
  annotations in PHP source code. By setting it to true, all Doctrine-style
  annotations are compacted in PHP files. You may also specify a list of
  annotations to ignore, which will be stripped while protecting the remaining
  annotations:

    {
        "annotations": {
            "ignore": [
                "author",
                "package",
                "version",
                "see"
            ]
        }
    }

  You may want to see this website for a list of annotations which are commonly
  ignored:

    https://github.com/herrera-io/php-annotations


  The banner (string) setting is the banner comment that will be used when a new
  stub is generated. The value of this setting must not already be enclosed
  within a comment block, as it will be automatically done for you.

  The banner-file (string) setting is like banner, except it is a path to the
  file that will contain the comment. Like banner, the comment must not already
  be enclosed in a comment block.

  The base-path (string) setting is used to specify where all of the relative
  file paths should resolve to. This does not, however, alter where the built
  PHAR will be stored (see: output). By default, the base path is the directory
  containing the configuration file.

  The blacklist (string[]) setting is a list of files that must not be added.
  The files blacklisted are the ones found using the other available
  configuration settings: directories, directories-bin, files, files-bin,
  finder, finder-bin. Note that directory separators are automatically corrected
  to the platform specific version.

  Assuming that the base directory path is /home/user/project:

    {
        "blacklist": [
            "path/to/file/1"
            "path/to/file/2"
        ],
        "directories": ["src"]
    }

  The following files will be blacklisted:

    - /home/user/project/src/path/to/file/1
    - /home/user/project/src/path/to/file/2

  But not these files:

    - /home/user/project/src/another/path/to/file/1
    - /home/user/project/src/another/path/to/file/2


  The bootstrap (string) setting allows you to specify a PHP file that will be
  loaded before the build or add commands are used. This is useful for loading
  third-party file contents compacting classes that were configured using the
  compactors setting.

  The chmod (string) setting is used to change the file permissions of the newly
  built PHAR. The string contains an octal value: 0750.

  Check the following link for more on the possible values:

    https://secure.php.net/manual/en/function.chmod.php


  The compactors (string[]) setting is a list of file contents compacting
  classes that must be registered. A file compacting class is used to reduce the
  size of a specific file type. The following is a simple example:

    use Herrera\\Box\\Compactor\\CompactorInterface;

    class MyCompactor implements CompactorInterface
    {
        public function compact(\$contents)
        {
            return trim(\$contents);
        }

        public function supports(\$file)
        {
            return (bool) preg_match('/\.txt/', \$file);
        }
    }

  The following compactors are included with Box:

    - Herrera\\Box\\Compactor\\Json
    - Herrera\\Box\\Compactor\\Php


  The compression (string) setting is the compression algorithm to use when the
  PHAR is built. The compression affects the individual files within the PHAR,
  and not the PHAR as a whole (Phar::compressFiles()). The following is a list
  of the signature algorithms listed on the help page:

    - BZ2 (Phar::BZ2)
    - GZ (Phar::GZ)
    - NONE (Phar::NONE)


  The directories (string[]) setting is a list of directory paths relative to
  base-path. All files ending in .php will be automatically compacted, have
  their placeholder values replaced, and added to the PHAR. Files listed in the
  blacklist setting will not be added.

  The directories-bin (string[]) setting is similar to directories, except all
  file types are added to the PHAR unmodified. This is suitable for directories
  containing images or other binary data.

  The extract (boolean) setting determines whether or not the generated stub
  should include a class to extract the PHAR. This class would be used if the
  PHAR is not available. (Increases stub file size.)

  The files (string[]) setting is a list of files paths relative to base-path.
  Each file will be compacted, have their placeholder files replaced, and added
  to the PHAR. This setting is not affected by the blacklist setting.

  The files-bin (string[]) setting is similar to files, except that all files
  are added to the PHAR unmodified. This is suitable for files such as images or
  those that contain binary data.

  The finder (array) setting is a list of JSON objects. Each object key is a
  name, and each value an argument for the methods in
  the Symfony\Component\Finder\Finder class. If an array of values is provided
  for a single key, the method will be called once per value in the array. Note
  that the paths specified for the "in" method are relative to base-path.

  The finder-bin (array) setting performs the same function, except all files
  found by the finder will be treated as binary files, leaving them unmodified.

  The datetime (string) setting is the name of a placeholder value that will be
  replaced in all non-binary files by the current datetime.

  Example: 2015-01-28 14:55:23

  The datetime_format (string) setting accepts a valid PHP date format. It can be used to change the format for the datetime setting.

  Example: Y-m-d H:i:s

  The git-commit (string) setting is the name of a placeholder value that will
  be replaced in all non-binary files by the current Git commit hash of the
  repository.

  Example: e558e335f1d165bc24d43fdf903cdadd3c3cbd03

  The git-commit-short (string) setting is the name of a placeholder value that
  will be replaced in all non-binary files by the current Git short commit hash
  of the repository.

  Example: e558e33

  The git-tag (string) setting is the name of a placeholder value that will be
  replaced in all non-binary files by the current Git tag of the repository.

  Examples:

   - 2.0.0
   - 2.0.0-2-ge558e33


  The git-version (string) setting is the name of a placeholder value that will
  be replaced in all non-binary files by the one of the following (in order):

    - The git repository's most recent tag.
    - The git repository's current short commit hash.

  The short commit hash will only be used if no tag is available.

  The intercept (boolean) setting is used when generating a new stub. If setting
  is set to true, the Phar::interceptFileFuncs(); method will be called in the
  stub.

  For more information:

    https://secure.php.net/manual/en/phar.interceptfilefuncs.php


  The key (string) setting is used to specify the path to the private key file.
  The private key file will be used to sign the PHAR using the OPENSSL signature
  algorithm. If an absolute path is not provided, the path will be relative to
  the current working directory.

  The key-pass (string, boolean) setting is used to specify the passphrase for
  the private key. If a string is provided, it will be used as is as the
  passphrase. If true is provided, you will be prompted for the passphrase.

  The main (string) setting is used to specify the file (relative to base-path)
  that will be run when the PHAR is executed from the command line. If the file
  was not added by any of the other file adding settings, it will be
  automatically added after it has been compacted and had its placeholder values
  replaced. The shebang line #! will be automatically removed if present.

  The map (array) setting is used to change where some (or all) files are stored
  inside the PHAR. The key is a beginning of the relative path that will be
  matched against the file being added to the PHAR. If the key is a match, the
  matched segment will be replaced with the value. If the key is empty, the
  value will be prefixed to all paths (except for those already matched by an
  earlier key).


    {
      "map": [
        { "my/test/path": "src/Test" },
        { "": "src/Another" }
      ]
    }


  (with the files)


    1. my/test/path/file.php
    2. my/test/path/some/other.php
    3. my/test/another.php


  (will be stored as)


    1. src/Test/file.php
    2. src/Test/some/other.php
    3. src/Another/my/test/another.php


  The metadata (any) setting can be any value. This value will be stored as
  metadata that can be retrieved from the built PHAR (Phar::getMetadata()).

  The mimetypes (object) setting is used when generating a new stub. It is a map
  of file extensions and their mimetypes. To see a list of the default mapping,
  please visit:

    http://www.php.net/manual/en/phar.webphar.php

  The mung (array) setting is used when generating a new stub. It is a list of
  server variables to modify for the PHAR. This setting is only useful when the
  web setting is enabled.

  The not-found (string) setting is used when generating a new stub. It
  specifies the file that will be used when a file is not found inside the PHAR.
  This setting is only useful when web setting is enabled.

  The output (string) setting specifies the file name and path of the newly
  built PHAR. If the value of the setting is not an absolute path, the path will
  be relative to the current working directory.

  The replacements (object) setting is a map of placeholders and their values.
  The placeholders are replaced in all non-binary files with the specified
  values.

  The shebang (string) setting is used to specify the shebang line used when
  generating a new stub. By default, this line is used:

    #!/usr/bin/env php

  The shebang line can be removed altogether if false or an empty string is
  provided.

  The stub (string, boolean) setting is used to specify the location of a stub
  file, or if one should be generated. If a path is provided, the stub file will
  be used as is inside the PHAR. If true is provided, a new stub will be
  generated. If false (or nothing) is provided, the default stub used by the
  PHAR class will be used.

  The web (boolean) setting is used when generating a new stub. If true is
  provided, Phar::webPhar() will be called in the stub.

OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $expected = <<<'OUTPUT'

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

Building the PHAR "/path/to/tmp/test.phar"
Private key passphrase:
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = $this->normalizeDisplay($phar->getStub());
        $expectedStub = <<<PHP
$shebang
<?php
/**
 * custom banner
 */
if (class_exists('Phar')) {
Phar::mapPhar('alias-test.phar');
require 'phar://' . __FILE__ . '/run.php';
}
__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/one/',
            '/one/test.php',
            '/run.php',
            '/sub/',
            '/sub/test.php',
            '/test.php',
            '/two/',
            '/two/test.png',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_with_complete_mapping(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $expected = <<<'OUTPUT'

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

Building the PHAR "/path/to/tmp/test.phar"
Private key passphrase:
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual, 'Expected logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        // Check PHAR content
        $actualStub = $this->normalizeDisplay($phar->getStub());
        $expectedStub = <<<PHP
$shebang
<?php
/**
 * custom banner
 */
if (class_exists('Phar')) {
Phar::mapPhar('alias-test.phar');
require 'phar://' . __FILE__ . '/other/run.php';
}
__HALT_COMPILER(); ?>

PHP;

        $this->assertSame($expectedStub, $actualStub);

        $this->assertSame(
            ['rand' => $rand],
            $phar->getMetadata(),
            'Expected PHAR metadata to be set'
        );

        $expectedFiles = [
            '/other/',
            '/other/one/',
            '/other/one/test.php',
            '/other/run.php',
            '/other/test.php',
            '/other/two/',
            '/other/two/test.png',
            '/sub/',
            '/sub/test.php',
        ];

        $actualFiles = $this->retrievePharFiles($phar);

        $this->assertSame($expectedFiles, $actualFiles);
    }

    public function test_it_can_build_a_PHAR_file_in_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
  - (all) > other/
? Adding finder files
? Adding binary finder files
? Adding directories
? Adding files
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Generating new stub
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_very_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Loading the bootstrap file "/path/to/tmp/bootstrap.php"
? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
  - (all) > other/
? Adding finder files
? Adding binary finder files
? Adding directories
? Adding files
? Adding main file: /path/to/tmp/run.php
    > other/run.php
? Generating new stub
  - Using custom shebang line: #!__PHP_EXECUTABLE__
  - Using custom banner: custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 493
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $expected = str_replace(
            '__PHP_EXECUTABLE__',
            (new PhpExecutableFinder())->find(),
            $expected
        );
        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_file_in_quiet_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => true,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_QUIET,
            ]
        );

        $expected = '';

        $actual = $commandTester->getDisplay(true);

        $this->assertSame($expected, $actual, 'Expected output logs to be identical');

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        // Check PHAR content
        $pharContents = file_get_contents('test.phar');
        $shebang = preg_quote($shebang, '/');

        $this->assertSame(
            1,
            preg_match(
                "/$shebang/",
                $pharContents
            )
        );
        $this->assertSame(
            1,
            preg_match(
                '/custom banner/',
                $pharContents
            )
        );

        $phar = new Phar('test.phar');

        $this->assertSame(['rand' => $rand], $phar->getMetadata());
    }

    public function test_it_can_build_a_PHAR_file_using_the_PHAR_default_stub(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => false,
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_file_using_a_custom_stub(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'custom_stub',
            $stub = <<<'PHP'
#!/usr/bin/php
<?php

//
// This is a custom stub: shebang & custom banner are not applied
//

if (class_exists('Phar')) {
    Phar::mapPhar('alias-test.phar');
    require 'phar://' . __FILE__ . '/other/run.php';
}
__HALT_COMPILER(); ?>

PHP
        );

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'alias-test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => 'a',
                    'files' => 'test.php',
                    'finder' => [['in' => 'one']],
                    'finder-bin' => [['in' => 'two']],
                    'key' => 'private.key',
                    'key-pass' => true,
                    'main' => 'run.php',
                    'map' => [
                        ['a/deep/test/directory' => 'sub'],
                        ['' => 'other/'],
                    ],
                    'metadata' => ['rand' => $rand = random_int(0, mt_getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $shebang,
                    'stub' => 'custom_stub',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        $commandTester->setInputs(['test']);
        $commandTester->execute(
            ['command' => 'build'],
            ['interactive' => true]
        );

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );

        $phar = new Phar('test.phar');

        $actualStub = $this->normalizeDisplay($phar->getStub());

        $this->assertSame($stub, $actualStub);
    }

    public function test_it_cannot_build_a_PHAR_using_unreadable_files(): void
    {
        touch('test.php');
        chmod('test.php', 0000);

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'files' => 'test.php',
                ]
            )
        );

        $commandTester = $this->getCommandTester();

        try {
            $commandTester->execute(
                ['command' => 'build'],
                [
                    'interactive' => false,
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertTrue(true);
        }
    }

    public function test_it_can_build_a_PHAR_overwriting_an_existing_one_in_verbose_mode(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir002', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

? Removing the existing PHAR "/path/to/tmp/default.phar"
* Building the PHAR "/path/to/tmp/default.phar"
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Generating new stub
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_replacement_placeholder(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir001', $this->tmp);

        $commandTester = $this->getCommandTester();

        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/default.phar"
? Setting replacement values
  + @name@: world
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Generating new stub
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_custom_banner(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir003', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            [
                'command' => 'build',
            ],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Generating new stub
  - Using custom banner from file: /path/to/tmp/banner
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_a_stub_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir004', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Using stub file: /path/to/tmp/stub.php
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_with_the_default_stub_file(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir005', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files
? Using default stub
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_build_a_PHAR_with_compressed_code(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir006', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/test.phar"
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Generating new stub
? Compressing with the algorithm "GZ"
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $builtPhar = new Phar('test.phar');

        $this->assertFalse($builtPhar->isCompressed()); // TODO: this is a bug, see https://github.com/humbug/box/issues/20
        $this->assertTrue($builtPhar['test.php']->isCompressed());

        $this->assertSame(
            'Hello!',
            exec('php test.phar'),
            'Expected the PHAR to be executable'
        );
    }

    public function test_it_can_build_a_PHAR_in_a_non_existent_directory(): void
    {
        (new Filesystem())->mirror(self::FIXTURES_DIR.'/dir007', $this->tmp);

        $commandTester = $this->getCommandTester();
        $commandTester->execute(
            ['command' => 'build'],
            [
                'interactive' => false,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box (repo)

* Building the PHAR "/path/to/tmp/foo/bar/test.phar"
? No compactor to register
? Adding files
? Adding main file: /path/to/tmp/test.php
? Generating new stub
? No compression
* Done.

 // Size: 100B
 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $actual = $this->normalizeDisplay($commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertSame(
            'Hello!',
            exec('php foo/bar/test.phar'),
            'Expected the PHAR to be executable'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Build();
    }

    private function normalizeDisplay(string $display)
    {
        $display = str_replace($this->tmp, '/path/to/tmp', $display);

        $display = preg_replace(
            '/\/\/ Size: \d+\.\d{2}K?B/',
            '// Size: 100B',
            $display
        );

        $display = preg_replace(
            '/\/\/ Memory usage: \d+\.\d{2}MB \(peak: \d+\.\d{2}MB\), time: \d+\.\d{2}s/',
            '// Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s',
            $display
        );

        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines
        );

        return implode("\n", $lines);
    }

    private function retrievePharFiles(Phar $phar, Traversable $traversable = null): array
    {
        $root = 'phar://'.str_replace('\\', '/', realpath($phar->getPath())).'/';

        if (null === $traversable) {
            $traversable = $phar;
        }

        $paths = [];

        foreach ($traversable as $fileInfo) {
            /** @var PharFileInfo $fileInfo */
            $fileInfo = $phar[str_replace($root, '', $fileInfo->getPathname())];

            $path = substr($fileInfo->getPathname(), strlen($root) - 1);

            if ($fileInfo->isDir()) {
                $path .= '/';

                $paths = array_merge(
                    $paths,
                    $this->retrievePharFiles(
                        $phar,
                        new DirectoryIterator($fileInfo->getPathname())
                    )
                );
            }

            $paths[] = $path;
        }

        sort($paths);

        return array_unique($paths);
    }
}
