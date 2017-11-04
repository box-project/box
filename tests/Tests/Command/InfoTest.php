<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\Info;
use KevinGH\Box\Test\CommandTestCase;
use Phar;

class InfoTest extends CommandTestCase
{
    public function testGetInfo()
    {
        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'info'
            )
        );

        $version = Phar::apiVersion();
        $compression = '  - ' . join("\n  - ", Phar::getSupportedCompression());
        $signatures = '  - ' . join("\n  - ", Phar::getSupportedSignatures());
        $expected = <<<OUTPUT
API Version: $version

Supported Compression:
$compression

Supported Signatures:
$signatures

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    public function testGetInfoPhar()
    {
        $phar = new Phar('test.phar');
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!\n";');

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'info',
                'phar' => 'test.phar'
            )
        );

        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    public function testGetInfoPharList()
    {
        $phar = new Phar('test.phar');
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!\n";');
        $phar->addFromString('a/b/c/e.php', '<?php echo "Compressed!\n";');
        $phar->setMetadata(array('test' => 123));

        /** @var \PharFileInfo[] $phar */
        $phar['a/b/c/e.php']->compress(Phar::BZ2);

        /** @var Phar $phar */
        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'info',
                'phar' => 'test.phar',
                '--list' => true,
                '--metadata' => true,
            )
        );

        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

Contents:
a/
  b/
    c/
      d.php
      e.php [BZ2]

Metadata:
array (
  'test' => 123,
)

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    public function testGetInfoPharListFlat()
    {
        $phar = new Phar('test.phar');
        $phar->addFromString('a/b/c/d.php', '<?php echo "Hello!\n";');

        $version = $phar->getVersion();
        $signature = $phar->getSignature();

        unset($phar);

        $tester = $this->getTester();
        $tester->execute(
            array(
                'command' => 'info',
                'phar' => 'test.phar',
                '--mode' => 'flat',
                '--list' => true
            )
        );

        $ds = DIRECTORY_SEPARATOR;
        $expected = <<<OUTPUT
API Version: $version

Archive Compression: None

Signature: {$signature['hash_type']}

Signature Hash: {$signature['hash']}

Contents:
a
a{$ds}b
a{$ds}b{$ds}c
a{$ds}b{$ds}c{$ds}d.php

OUTPUT;

        $this->assertEquals($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Info();
    }
}
