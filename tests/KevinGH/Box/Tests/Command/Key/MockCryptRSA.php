<?php

namespace KevinGH\Box\Tests\Command\Key;

use phpseclib\Crypt\RSA;

class MockCryptRSA extends RSA
{
    public function getPublicKey($type = self::PUBLIC_FORMAT_PKCS1)
    {
        return false;
    }
}
