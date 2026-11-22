<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    // PHP CS Fixer only knows PHP's syntax up to 8.5 and refuses to run on 8.6 otherwise: drop this line once a release
    // supports 8.6 — and until then, write no syntax 8.6 brings.
    ->setUnsupportedPhpVersionAllowed(true)
    ->setFinder($finder)
    ->setCacheFile('var/.php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'ordered_class_elements' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_to_comment' => false,
    ]);
