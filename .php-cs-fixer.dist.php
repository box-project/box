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
    ->notName('*-phar-stub.php')
    // The leading backslash is what this E2E fixture tests.
    ->notPath('build/dir014/index.php');

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
    'new_with_parentheses' => false,    // Should be done in fidry/php-cs-fixer-config
    'php_unit_data_provider_method_order' => false,
    'random_api_migration' => false,
];

$config = new FidryConfig('', 82_000);
$config->addRules($overriddenRules);
$config->setCacheFile(__DIR__.'/dist/.php-cs-fixer.cache');
$config->setFinder($finder);

return $config;
