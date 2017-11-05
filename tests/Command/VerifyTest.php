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
use Phar;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

/**
 * @coversNothing
 */
class VerifyTest extends CommandTestCase
{
    public function testExecuteExtension(): void
    {
        file_put_contents('test.php', '<?php echo "Hello!";');

        $phar = new Phar('test.phar');
        $phar->addFile('test.php', 'test.php');

        $signature = $phar->getSignature();

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'verify',
                'phar' => 'test.phar',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT
Verifying the Phar...
The Phar passed verification.
{$signature['hash_type']} Signature:
{$signature['hash']}

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteLibrary(): void
    {
        file_put_contents('test.php', '<?php echo "Hello!";');

        $phar = new Phar('test.phar');
        $phar->addFile('test.php', 'test.php');

        $signature = $phar->getSignature();

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'verify',
                'phar' => 'test.phar',
                '--no-extension' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<OUTPUT
Verifying the Phar...
The Phar passed verification.
{$signature['hash_type']} Signature:
{$signature['hash']}

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteNotExist(): void
    {
        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'verify',
                'phar' => 'test.phar',
            ]
        );

        $expected = <<<'OUTPUT'
The path "test.phar" is not a file or does not exist.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteFailed(): void
    {
        file_put_contents('test.phar', 'bad');

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'verify',
                'phar' => 'test.phar',
            ]
        );

        $expected = <<<'OUTPUT'
The Phar failed verification.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteFailedVerbose(): void
    {
        file_put_contents('test.phar', 'bad');

        $tester = $this->getTester();

        try {
            $tester->execute(
                [
                    'command' => 'verify',
                    'phar' => 'test.phar',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );
        } catch (UnexpectedValueException $exception) {
        }

        $expected = <<<'OUTPUT'
Verifying the Phar...
The Phar failed verification.

OUTPUT;

        $this->assertTrue(isset($exception));
        $this->assertSame($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Verify();
    }
}
