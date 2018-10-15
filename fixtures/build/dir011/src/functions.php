<?php

declare(strict_types=1);

namespace {
    function salute(): void
    {
        echo 'Hello';
    }
}

namespace Foo {
    function back(): void
    {
        echo '!';
    }
}
