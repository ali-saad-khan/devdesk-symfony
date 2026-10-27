<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->exclude('Migrations');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
        'yoda_style' => ['equal' => true, 'identical' => true, 'less_and_greater' => false],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
    ])
    ->setFinder($finder);
