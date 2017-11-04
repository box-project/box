<?php

namespace KevinGH\Box\Helper;

use phpseclib\Crypt\RSA;
use Symfony\Component\Console\Helper\Helper;

/**
 * A phpseclib helper.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class PhpSecLibHelper extends Helper
{
    /**
     * Returns a new instance of Crypt_RSA.
     *
     * @return Crypt_RSA The instance.
     */
    public function cryptRSA()
    {
        return new RSA();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'phpseclib';
    }
}
