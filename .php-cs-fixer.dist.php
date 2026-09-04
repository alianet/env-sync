<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests'])->append([__FILE__, __DIR__.'/bin/env-sync']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
