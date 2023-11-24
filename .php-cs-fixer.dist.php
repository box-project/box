<?php declare(strict_types=1);

use Fidry\PhpCsFixerConfig\FidryConfig;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        'fixtures',
        'bin',
        'src',
        'tests',
    ])
    ->append([
        'bin/box',
        'bin/generate_default_stub',
    ])
    ->exclude([
        'bench',
        'build/dir018/var',
    ])
    ->notName('*-phar-stub.php');

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
];

$config = new FidryConfig('', 82_000);
$config->addRules($overriddenRules);
$config->setCacheFile(__DIR__.'/dist/.php-cs-fixer.cache');
$config->setFinder($finder);

return $config;
