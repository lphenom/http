<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                               => true,
        'declare_strict_types'                 => true,
        'array_syntax'                         => ['syntax' => 'short'],
        'no_unused_imports'                    => true,
        'ordered_imports'                      => ['sort_algorithm' => 'alpha'],
        'trailing_comma_in_multiline'          => ['elements' => ['arrays', 'arguments']],
        'single_quote'                         => true,
        'no_whitespace_in_blank_line'          => true,
        'blank_line_after_namespace'           => true,
        'method_argument_space'                => ['on_multiline' => 'ensure_fully_multiline'],
        'return_type_declaration'              => ['space_before' => 'none'],
    ])
    ->setFinder($finder)
    ->setUsingCache(false);

