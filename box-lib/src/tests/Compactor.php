<?php

namespace Herrera\Box\Tests;

use Herrera\Box\Compactor\CompactorInterface;

class Compactor implements CompactorInterface
{
    public function compact($contents)
    {
        return trim($contents);
    }

    public function supports($file)
    {
        return ('php' === pathinfo($file, PATHINFO_EXTENSION));
    }
}
