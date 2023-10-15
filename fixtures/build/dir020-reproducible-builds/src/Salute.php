<?php

declare(strict_types=1);

namespace App;

use const PHP_EOL;

final class Salute
{
    public function __invoke(): void
    {
        echo 'Hello world!'.PHP_EOL;
    }
}