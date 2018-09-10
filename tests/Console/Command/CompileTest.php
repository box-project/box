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

namespace KevinGH\Box\Console\Command;

use DirectoryIterator;
use Generator;
use InvalidArgumentException;
use KevinGH\Box\Compactor\Php;
use KevinGH\Box\Console\DisplayNormalizer;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use Phar;
use PharFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Traversable;
use function extension_loaded;
use function file_get_contents;
use function file_put_contents;
use function iterator_to_array;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\FileSystem\mirror;
use function KevinGH\Box\FileSystem\rename;
use function preg_match;
use function preg_replace;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;

/**
 * @covers \KevinGH\Box\Console\Command\Compile
 */
class CompileTest extends CommandTestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/build';

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();

        $this->commandTester = new class($this->application->get($this->getCommand()->getName())) extends CommandTester {
            /**
             * {@inheritdoc}
             */
            public function execute(array $input, array $options = [])
            {
                if ('compile' === $input['command']) {
                    $input['--no-parallel'] = null;
                }

                return parent::execute($input, $options);
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand(): Command
    {
        return new Compile();
    }

    public function test_it_can_build_a_PHAR_file_in_debug_mode(): void
    {
        mirror(self::FIXTURES_DIR.'/dir000', $this->tmp);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => [
                        'multiline',
                        'custom banner',
                    ],
                    'chmod' => '0755',
                    'compactors' => [Php::class],
                    'directories' => ['a'],
                    'files' => ['test.php'],
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

        $this->assertDirectoryNotExists('.box_dump');

        $this->commandTester->setInputs(['test']);    // Set input for the passphrase
        $this->commandTester->execute(
            [
                'command' => 'compile',
                '--debug' => null,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ]
        );

        $xdebugLog = extension_loaded('xdebug')
            ? '[debug] The xdebug extension is loaded (2.6.0)
[debug] No restart (BOX_ALLOW_XDEBUG=1)'
            : '[debug] The xdebug extension is not loaded'
        ;

        $expected = <<<OUTPUT
[debug] Checking BOX_ALLOW_XDEBUG
$xdebugLog
[debug] Disabled parallel processing

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box version 3.x-dev@151e40a


 // Loading the configuration file "/path/to/box.json.dist".

? Removing the existing PHAR "/path/to/tmp/test.phar"
* Building the PHAR "/path/to/tmp/test.phar"
? Registering compactors
  + KevinGH\Box\Compactor\Php
? Mapping paths
  - a/deep/test/directory > sub
? Adding main file: /path/to/tmp/run.php
? Adding requirements checker
? Adding binary files
    > 1 file(s)
? Adding files
    > 4 file(s)
? Generating new stub
  - Using shebang line: #!__PHP_EXECUTABLE__
  - Using banner:
    > multiline
    > custom banner
? Setting metadata
  - array (
  'rand' => $rand,
)
? Dumping the Composer autoloader
? Removing the Composer dump artefacts
? No compression
? Signing using a private key
Private key passphrase:
? Setting file permissions to 0755
* Done.

 // PHAR: 45 files (100B)
 // You can inspect the generated PHAR with the "info" command.

 // Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s


OUTPUT;

        $expected = str_replace(
            '__PHP_EXECUTABLE__',
            (new PhpExecutableFinder())->find(),
            $expected
        );
        $actual = $this->normalizeDisplay($this->commandTester->getDisplay(true));

        $this->assertSame($expected, $actual);

        $this->assertDirectoryExists('.box_dump');

        $expectedFiles = [
            '.box_dump/.box/.requirements.php',
            '.box_dump/.box/bin/check-requirements.php',
            '.box_dump/.box/check_requirements.php',
            '.box_dump/.box/composer.json',
            '.box_dump/.box/composer.lock',
            '.box_dump/.box/src/Checker.php',
            '.box_dump/.box/src/IO.php',
            '.box_dump/.box/src/IsExtensionFulfilled.php',
            '.box_dump/.box/src/IsFulfilled.php',
            '.box_dump/.box/src/IsPhpVersionFulfilled.php',
            '.box_dump/.box/src/Printer.php',
            '.box_dump/.box/src/Requirement.php',
            '.box_dump/.box/src/RequirementCollection.php',
            '.box_dump/.box/src/Terminal.php',
            '.box_dump/.box/vendor/autoload.php',
            '.box_dump/.box/vendor/composer/autoload_classmap.php',
            '.box_dump/.box/vendor/composer/autoload_namespaces.php',
            '.box_dump/.box/vendor/composer/autoload_psr4.php',
            '.box_dump/.box/vendor/composer/autoload_real.php',
            '.box_dump/.box/vendor/composer/autoload_static.php',
            '.box_dump/.box/vendor/composer/ClassLoader.php',
            '.box_dump/.box/vendor/composer/installed.json',
            '.box_dump/.box/vendor/composer/LICENSE',
            '.box_dump/.box/vendor/composer/semver/LICENSE',
            '.box_dump/.box/vendor/composer/semver/src/Comparator.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/AbstractConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/Constraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/ConstraintInterface.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/EmptyConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Constraint/MultiConstraint.php',
            '.box_dump/.box/vendor/composer/semver/src/Semver.php',
            '.box_dump/.box/vendor/composer/semver/src/VersionParser.php',
            '.box_dump/.box_configuration',
            '.box_dump/one/test.php',
            '.box_dump/run.php',
            '.box_dump/sub/test.php',
            '.box_dump/test.php',
            '.box_dump/two/test.png',
            '.box_dump/vendor/autoload.php',
            '.box_dump/vendor/composer/autoload_classmap.php',
            '.box_dump/vendor/composer/autoload_namespaces.php',
            '.box_dump/vendor/composer/autoload_psr4.php',
            '.box_dump/vendor/composer/autoload_real.php',
            '.box_dump/vendor/composer/autoload_static.php',
            '.box_dump/vendor/composer/ClassLoader.php',
            '.box_dump/vendor/composer/LICENSE',
        ];

        $actualFiles = $this->normalizePaths(
            iterator_to_array(
                Finder::create()->files()->in('.box_dump')->ignoreDotFiles(false)
            )
        );

        $this->assertSame($expectedFiles, $actualFiles);

        $shebang = sprintf('#!%s', (new PhpExecutableFinder())->find());

        $expectedDumpedConfig = <<<EOF
//
// Processed content of the configuration file "/path/to/box.json" dumped for debugging purposes
// Time: 2018-05-24T20:59:15+00:00
//

KevinGH\Box\Configuration {#140
  -file: "/path/to/box.json"
  -fileMode: 493
  -alias: "test.phar"
  -basePath: "/path/to"
  -composerJson: array:2 [
    0 => "/path/to/composer.json"
    1 => array:1 [
      "autoload" => array:1 [
        "classmap" => array:1 [
          0 => "./"
        ]
      ]
    ]
  ]
  -composerLock: array:2 [
    0 => null
    1 => null
  ]
  -files: array:4 [
    0 => SplFileInfo {#140
      path: "/path/to"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/test.php"
      extension: "php"
      realPath: "/path/to/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 306
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    1 => SplFileInfo {#140
      path: "/path/to"
      filename: "composer.json"
      basename: "composer.json"
      pathname: "/path/to/composer.json"
      extension: "json"
      realPath: "/path/to/composer.json"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 54
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    2 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: "deep/test/directory"
      -relativePathname: "deep/test/directory/test.php"
      path: "/path/to/a/deep/test/directory"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/a/deep/test/directory/test.php"
      extension: "php"
      realPath: "/path/to/a/deep/test/directory/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
    3 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: ""
      -relativePathname: "test.php"
      path: "/path/to/one"
      filename: "test.php"
      basename: "test.php"
      pathname: "/path/to/one/test.php"
      extension: "php"
      realPath: "/path/to/one/test.php"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
  ]
  -binaryFiles: array:1 [
    0 => Symfony\Component\Finder\SplFileInfo {#140
      -relativePath: ""
      -relativePathname: "test.png"
      path: "/path/to/two"
      filename: "test.png"
      basename: "test.png"
      pathname: "/path/to/two/test.png"
      extension: "png"
      realPath: "/path/to/two/test.png"
      aTime: 2018-05-24 20:59:15
      mTime: 2018-05-24 20:59:15
      cTime: 2018-05-24 20:59:15
      inode: 33452869
      size: 0
      perms: 0100644
      owner: 501
      group: 20
      type: "file"
      writable: true
      readable: true
      executable: false
      file: true
      dir: false
      link: false
    }
  ]
  -dumpAutoload: true
  -excludeComposerFiles: true
  -compactors: array:1 [
    0 => KevinGH\Box\Compactor\Php {#140
      -converter: Herrera\Annotations\Convert\ToString {#140
        -break: "\\n"
        -char: " "
        -level: null
        -space: false
        -size: 0
        #result: null
        #tokens: null
      }
      -tokenizer: Herrera\Annotations\Tokenizer {#140
        -aliases: []
        -ignored: []
        -lexer: Doctrine\Common\Annotations\DocLexer {#140
          #noCase: array:9 [
            "@" => 101
            "," => 104
            "(" => 109
            ")" => 103
            "{" => 108
            "}" => 102
            "=" => 105
            ":" => 112
            "\" => 107
          ]
          #withCase: array:3 [
            "true" => 110
            "false" => 106
            "null" => 111
          ]
          -input: null
          -tokens: []
          -position: 0
          -peek: 0
          +lookahead: null
          +token: null
        }
      }
      -extensions: array:1 [
        0 => "php"
      ]
    }
  ]
  -compressionAlgorithm: null
  -mainScriptPath: "/path/to/run.php"
  -mainScriptContents: """
    <?php\\n
    \\n
    declare(strict_types=1);\\n
    \\n
    /*\\n
     * This file is part of the box project.\\n
     *\\n
     * (c) Kevin Herrera <kevin@herrera.io>\\n
     *     Théo Fidry <theo.fidry@gmail.com>\\n
     *\\n
     * This source file is subject to the MIT license that is bundled\\n
     * with this source code in the file LICENSE.\\n
     */\\n
    \\n
    require 'test.php';\\n
    """
  -map: null
  -fileMapper: KevinGH\Box\MapFile {#140
    -basePath: "/path/to"
    -map: array:1 [
      0 => array:1 [
        "a/deep/test/directory" => "sub"
      ]
    ]
  }
  -metadata: array:1 [
    "rand" => $rand
  ]
  -tmpOutputPath: "/path/to/test.phar"
  -outputPath: "/path/to/test.phar"
  -privateKeyPassphrase: null
  -privateKeyPath: "private.key"
  -isPrivateKeyPrompt: true
  -processedReplacements: []
  -shebang: "$shebang"
  -signingAlgorithm: 2
  -stubBannerContents: """
    multiline\\n
    custom banner
    """
  -stubBannerPath: null
  -stubPath: null
  -isInterceptFileFuncs: false
  -isStubGenerated: true
  -checkRequirements: true
}

EOF;

        $actualDumpedConfig = str_replace(
            $this->tmp,
            '/path/to',
            file_contents('.box_dump/.box_configuration')
        );

        $actualDumpedConfig = preg_replace(
            '/ \{#\d{3,}/',
            ' {#140',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/Time: \d{4,}-\d{2,}-\d{2,}T\d{2,}:\d{2,}:\d{2,}\+\d{2,}:\d{2,}/',
            'Time: 2018-05-24T20:59:15+00:00',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/([a-z]Time): \d{4,}-\d{2,}-\d{2,} \d{2,}:\d{2,}:\d{2,}/',
            '$1: 2018-05-24 20:59:15',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/inode: \d+/',
            'inode: 33452869',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/perms: \d+/',
            'perms: 0100644',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/owner: \d+/',
            'owner: 501',
            $actualDumpedConfig
        );

        $actualDumpedConfig = preg_replace(
            '/group: \d+/',
            'group: 20',
            $actualDumpedConfig
        );

        $this->assertSame($expectedDumpedConfig, $actualDumpedConfig);
    }

    public function provideAliasConfig(): Generator
    {
        yield [true];
        yield [false];
    }

    private function normalizeDisplay(string $display)
    {
        $display = str_replace($this->tmp, '/path/to/tmp', $display);

        $display = preg_replace(
            '/Loading the configuration file[\s\n]+.*[\s\n\/]+.*box\.json[comment\<\>\n\s\/]*"\./',
            'Loading the configuration file "/path/to/box.json.dist".',
            $display
        );

        $display = preg_replace(
            '/You can inspect the generated PHAR( | *\n *\/\/ *)with( | *\n *\/\/ *)the( | *\n *\/\/ *)"info"( | *\n *\/\/ *)command/',
            'You can inspect the generated PHAR with the "info" command',
            $display
        );

        $display = preg_replace(
            '/\/\/ PHAR: (\d+ files?) \(\d+\.\d{2}K?B\)/',
            '// PHAR: $1 (100B)',
            $display
        );

        $display = preg_replace(
            '/\/\/ Memory usage: \d+\.\d{2}MB \(peak: \d+\.\d{2}MB\), time: \d+\.\d{2}s/',
            '// Memory usage: 5.00MB (peak: 10.00MB), time: 0.00s',
            $display
        );

        $display = preg_replace(
            '/Box version .+@[a-z\d]{7}/',
            'Box version 3.x-dev@151e40a',
            $display
        );

        return DisplayNormalizer::removeTrailingSpaces($display);
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
