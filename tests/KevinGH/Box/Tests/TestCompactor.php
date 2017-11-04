<?php

namespace KevinGH\Box\Tests;

use Herrera\Box\Compactor\CompactorInterface;

class TestCompactor implements CompactorInterface
{
    public function compact($contents)
    {
    }

    public function supports($file)
    {
    }
}
