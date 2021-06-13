<?php
/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0.0-rc.1|configurator
 * you can change this configuration by importing this file.
 */
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP71Migration' => true, // @PHP72Migration does not exist
        '@PHP71Migration:risky' => true, // @PHP72Migration:risky does not exist
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'native_constant_invocation' => true,
        'native_function_invocation' => [
            'strict' => false,
            'include' => ['@compiler_optimized'],
        ],
        'no_superfluous_phpdoc_tags' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'php_unit_method_casing' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
        'phpdoc_to_comment' => false,
        'void_return' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    )
;
