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

namespace KevinGH\Box\Command\Key;

use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\FixedResponse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class ExtractTest extends CommandTestCase
{
    public function testExecute(): void
    {
        file_put_contents(
            'test.key',
            <<<'KEY'
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: DES-EDE3-CBC,FCBE82562DA52F8D

uJDjwVSvrhznj/eCq8+J7jQLAoqiYYVPbiRMGdZ8bz+nK6p1vCNvySXlGBgaVZ+9
OzDkE7eEbZaIkwtI7gdAeRTmFpa/7xVfJdK85HFC9+ei2QDxCYFFl4Zx7/m6Ymc0
zYGQhiOoQkt1GRjqWxvWC377h7PEz1Rh+GXxNzyRb5fteRGqrZHzp2kL36LvW5Ou
ILBxr5lwCHFKDY786W3ni77D8bNv0NiVKo0ljbKn/L3st+8erQRIaJ+bUobYIcmB
erqhP0vhufhAcJg0nKbQvtkY5GYmuof/MV6yN3Czqdoga5jjvl7PegOUvDJ3YbNB
sVfvUmDCRaojchJP8Cp/KcvkcEul2U4158QPr4opEEzemFqy5i9VYEGpDIZlPWjZ
AzcVp7Y/MqjdQLiSRYu6fsQvAEAauJD9wETXLWgYfSw=
-----END RSA PRIVATE KEY-----
KEY
        );

        $this->app->getHelperSet()->set(new FixedResponse('test'));

        $tester = $this->getCommandTester();
        $tester->execute(
            [
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub',
                '--prompt' => true,
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Extracting public key...
Writing public key...

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));

        $this->assertRegExp('/PUBLIC KEY/', file_get_contents('test.pub'));
    }

    public function testExecuteParseFail(): void
    {
        file_put_contents('test.key', 'bad');

        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub',
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The private key could not be parsed.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteExtractFail(): void
    {
        file_put_contents(
            'test.key',
            <<<'KEY'
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: DES-EDE3-CBC,FCBE82562DA52F8D

uJDjwVSvrhznj/eCq8+J7jQLAoqiYYVPbiRMGdZ8bz+nK6p1vCNvySXlGBgaVZ+9
OzDkE7eEbZaIkwtI7gdAeRTmFpa/7xVfJdK85HFC9+ei2QDxCYFFl4Zx7/m6Ymc0
zYGQhiOoQkt1GRjqWxvWC377h7PEz1Rh+GXxNzyRb5fteRGqrZHzp2kL36LvW5Ou
ILBxr5lwCHFKDY786W3ni77D8bNv0NiVKo0ljbKn/L3st+8erQRIaJ+bUobYIcmB
erqhP0vhufhAcJg0nKbQvtkY5GYmuof/MV6yN3Czqdoga5jjvl7PegOUvDJ3YbNB
sVfvUmDCRaojchJP8Cp/KcvkcEul2U4158QPr4opEEzemFqy5i9VYEGpDIZlPWjZ
AzcVp7Y/MqjdQLiSRYu6fsQvAEAauJD9wETXLWgYfSw=
-----END RSA PRIVATE KEY-----
KEY
        );

        $this->app->getHelperSet()->set(new FixedResponse('test'));
        $this->app->getHelperSet()->set(new MockPhpSecLibHelper());

        $tester = $this->getCommandTester();
        $exit = $tester->execute(
            [
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub',
                '--prompt' => true,
            ]
        );

        $this->assertSame(1, $exit);
        $this->assertSame(
            "The public key could not be retrieved.\n",
            $this->getOutput($tester)
        );
    }

    protected function getCommand(): Command
    {
        return new Extract();
    }
}
