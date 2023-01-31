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

use Fidry\PhpCsFixerConfig\FidryConfig;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        'src',
        'tests',
    ])
    ->append([
        'check-requirements.php',
        '.php-cs-fixer.dist.php',
        'scoper.inc.php',
    ]);

$overriddenRules = [
    'header_comment' => [
        'header' => <<<'EOF'
            This file is part of the box project.

            (c) Kevin Herrera <kevin@herrera.io>
                Théo Fidry <theo.fidry@gmail.com>

            This source file is subject to the MIT license that is bundled
            with this source code in the file LICENSE.
            EOF,
        'location' => 'after_declare_strict',
    ],
    'mb_str_functions' => false,
    'no_trailing_whitespace_in_string' => false,
    'use_arrow_functions' => false,
    'trailing_comma_in_multiline' => [
        'elements' => ['arrays'],
    ],
];

$config = new FidryConfig('', 74_000);
$config->addRules($overriddenRules);
$config->setCacheFile(__DIR__.'/dist/.php-cs-fixer.cache');
$config->setFinder($finder);

return $config;
