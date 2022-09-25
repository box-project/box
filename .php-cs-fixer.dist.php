<?php declare(strict_types=1);

$header = <<<'EOF'
This file is part of the box project.

(c) Kevin Herrera <kevin@herrera.io>
    Théo Fidry <theo.fidry@gmail.com>

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

$config = (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP71Migration:risky' => true,
        '@PHP70Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'align_multiline_comment' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => [
                'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],
        'blank_line_between_import_groups' => false,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_typehint' => true,
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
        'header_comment' => ['header' => $header],
        'heredoc_to_nowdoc' => true,
        'heredoc_indentation' => true,
        'list_syntax' => ['syntax' => 'short'],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'native_function_invocation' => false,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'continue',
                'extra',
                'return',
                'throw',
                'use',
                'parenthesis_brace_block',
                'square_brace_block',
                'curly_brace_block'
            ]
        ],
        'no_null_property_initialization' => true,
        'echo_tag_syntax' => true,
        'no_superfluous_elseif' => true,
        'no_unneeded_curly_braces' => true,
        'no_unneeded_final_method' => true,
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'php_unit_test_class_requires_covers' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => true,
        'phpdoc_types_order' => true,
        'php_unit_method_casing' => [
            'case' => 'snake_case',
        ],
        'semicolon_after_instruction' => true,
        'single_line_throw' => false,
        'strict_param' => true,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'arrays',
                'arguments',
                'parameters',
            ],
        ],
        'yoda_style' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in('fixtures')
            ->in('src')
            ->in('tests')
            ->append(['bin/box'])
            ->notName('default_stub.php')
            ->exclude('check-requirements')
            ->exclude('fixtures/build/dir012/var')
    )
;

(new PhpCsFixer\FixerFactory())
    ->registerBuiltInFixers()
    ->registerCustomFixers($config->getCustomFixers())
    ->useRuleSet(new PhpCsFixer\RuleSet\RuleSet($config->getRules()))
;

return $config;
