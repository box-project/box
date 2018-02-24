<?php

declare(strict_types=1);

namespace KevinGH\Box\Console;


final class DisplayNormalizer
{
    public static function removeTrailingSpaces(string $display): string
    {
        $lines = explode("\n", $display);

        $lines = array_map(
            'rtrim',
            $lines
        );

        return implode("\n", $lines);
    }

    private function __construct()
    {
    }
}