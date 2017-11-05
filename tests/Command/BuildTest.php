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

use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\FixedResponse;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @coversNothing
 */
class BuildTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->getHelperSet()->set(new FixedResponse('test'));
    }

    public function getPrivateKey()
    {
        return [
            <<<'KEY'
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
            'test',
        ];
    }

    public function testBuild(): void
    {
        $key = $this->getPrivateKey();

        $php = new PhpExecutableFinder();
        $php = '#!'.$php->find();

        mkdir('a/deep/test/directory', 0755, true);
        touch('a/deep/test/directory/test.php');

        mkdir('one');
        mkdir('two');
        touch('test.phar');
        touch('test.phar.pubkey');
        touch('one/test.php');
        touch('two/test.png');
        touch('bootstrap.php');
        file_put_contents('private.key', $key[0]);
        file_put_contents('test.php', '<?php echo "Hello, world!\n";');
        file_put_contents('run.php', '<?php require "test.php";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner' => 'custom banner',
                    'bootstrap' => 'bootstrap.php',
                    'chmod' => '0755',
                    'compactors' => ['Herrera\\Box\\Compactor\\Php'],
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
                    'metadata' => ['rand' => $rand = random_int(0, getrandmax())],
                    'output' => 'test.phar',
                    'shebang' => $php,
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $ds = DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
? Loading bootstrap file: {$dir}bootstrap.php
? Removing previously built Phar...
* Building...
? Output path: {$dir}test.phar
? Registering compactors...
  + Herrera\\Box\\Compactor\\Php
? Mapping paths:
  - a{$ds}deep{$ds}test{$ds}directory > sub
  - (all) > other/
? Adding Finder files...
  + {$dir}one{$ds}test.php
    > other{$ds}one{$ds}test.php
? Adding binary Finder files...
  + {$dir}two{$ds}test.png
    > other{$ds}two{$ds}test.png
? Adding directories...
  + {$dir}a{$ds}deep{$ds}test{$ds}directory{$ds}test.php
    > sub{$ds}test.php
? Adding files...
  + {$dir}test.php
    > other{$ds}test.php
? Adding main file: {$dir}run.php
    > other{$ds}run.php
? Generating new stub...
  - Using custom shebang line: $php
  - Using custom banner.
? Setting metadata...
? Signing using a private key...
? Setting file permissions...
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello, world!',
            exec('php test.phar')
        );

        $pharContents = file_get_contents('test.phar');
        $php = preg_quote($php, '/');

        $this->assertSame(1, preg_match("/$php/", $pharContents));
        $this->assertSame(1, preg_match('/custom banner/', $pharContents));

        $phar = new Phar('test.phar');

        $this->assertSame(['rand' => $rand], $phar->getMetadata());

        unset($phar);
    }

    public function testBuildNotReadable(): void
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

        $tester = $this->getTester();

        try {
            $tester->execute(
                ['command' => 'build'],
                ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
            );

            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The file "'.$this->dir.DIRECTORY_SEPARATOR.'test.php" is not readable.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @depends testBuild
     */
    public function testBuildReplacements(): void
    {
        file_put_contents('test.php', '<?php echo "Hello, @name@!\n";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'files' => 'test.php',
                    'main' => 'test.php',
                    'replacements' => ['name' => 'world'],
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            ['command' => 'build'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}default.phar
? Setting replacement values...
  + @name@: world
? Adding files...
  + {$dir}test.php
? Adding main file: {$dir}test.php
? Generating new stub...
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello, world!',
            exec('php default.phar')
        );
    }

    public function testBuildStubBannerFile(): void
    {
        file_put_contents('banner', 'custom banner');
        file_put_contents('test.php', '<?php echo "Hello!";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'banner-file' => 'banner',
                    'files' => 'test.php',
                    'main' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Adding main file: {$dir}test.php
? Generating new stub...
  - Using custom banner from file: {$dir}banner
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function testBuildStubFile(): void
    {
        touch('test.php');
        file_put_contents('stub.php', '<?php echo "Hello!"; __HALT_COMPILER();');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'files' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => 'stub.php',
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using stub file: {$dir}stub.php
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testBuildDefaultStub(): void
    {
        touch('test.php');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'files' => 'test.php',
                    'output' => 'test.phar',
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using default stub.
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testBuildCompressed(): void
    {
        file_put_contents('test.php', '<?php echo "Hello!";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'compression' => 'GZ',
                    'files' => 'test.php',
                    'main' => 'test.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'build',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $dir = $this->dir.DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Adding main file: {$dir}test.php
? Generating new stub...
? Compressing...
* Done.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertSame(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function testBuildQuiet(): void
    {
        mkdir('one');
        file_put_contents('one/test.php', '<?php echo "Hello!";');
        file_put_contents('run.php', '<?php require "one/test.php";');
        file_put_contents(
            'box.json',
            json_encode(
                [
                    'alias' => 'test.phar',
                    'finder' => [['in' => 'one']],
                    'main' => 'run.php',
                    'output' => 'test.phar',
                    'stub' => true,
                ]
            )
        );

        $tester = $this->getTester();
        $tester->execute(['command' => 'build']);

        $this->assertSame("Building...\n", $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Build();
    }
}
