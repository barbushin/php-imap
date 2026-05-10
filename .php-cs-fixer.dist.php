<?php

declare(strict_types=1);

/*
 * Keep the fixer config narrow and static-analysis-friendly.
 * In particular, do not enable phpdoc_to_comment:
 * Psalm/PHPStan assertions rely on docblocks staying docblocks.
 */
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'include' => true,
        'native_constant_invocation' => true,
        'native_function_invocation' => [
            'strict' => false,
            'include' => ['@compiler_optimized'],
        ],
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_superfluous_phpdoc_tags' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'php_unit_method_casing' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_var_without_name' => true,
        'void_return' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    )
;
