<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/bin', __DIR__ . '/config', __DIR__ . '/lang', __DIR__ . '/public', __DIR__ . '/tests'])
    ->exclude(['Views'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/storage/cache/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS2.0' => true,
        '@PHP83Migration' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
