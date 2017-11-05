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
     * @return Crypt_RSA the instance
     */
    public function cryptRSA()
    {
        return new RSA();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'phpseclib';
    }
}
