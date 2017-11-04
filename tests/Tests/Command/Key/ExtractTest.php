<?php

namespace KevinGH\Box\Tests\Command\Key;

use KevinGH\Box\Command\Key\Extract;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\FixedResponse;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractTest extends CommandTestCase
{
    public function testExecute()
    {
        file_put_contents(
            'test.key',
            <<<KEY
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

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub',
                '--prompt' => true
            ),
            array(
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE
            )
        );

        $expected = <<<OUTPUT
Extracting public key...
Writing public key...

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));

        $this->assertRegExp('/PUBLIC KEY/', file_get_contents('test.pub'));
    }

    public function testExecuteParseFail()
    {
        file_put_contents('test.key', 'bad');

        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub'
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The private key could not be parsed.\n",
            $this->getOutput($tester)
        );
    }

    public function testExecuteExtractFail()
    {
        file_put_contents(
            'test.key',
            <<<KEY
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

        $tester = $this->getTester();
        $exit = $tester->execute(
            array(
                'command' => 'key:extract',
                'private' => 'test.key',
                '--out' => 'test.pub',
                '--prompt' => true
            )
        );

        $this->assertEquals(1, $exit);
        $this->assertEquals(
            "The public key could not be retrieved.\n",
            $this->getOutput($tester)
        );
    }

    protected function getCommand()
    {
        return new Extract();
    }
}
