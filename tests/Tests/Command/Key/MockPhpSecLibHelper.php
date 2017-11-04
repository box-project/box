<?php

namespace KevinGH\Box\Tests\Command\Key;

use KevinGH\Box\Helper\PhpSecLibHelper;

class MockPhpSecLibHelper extends PhpSecLibHelper
{
    public function cryptRSA()
    {
        return new MockCryptRSA();
    }
}
