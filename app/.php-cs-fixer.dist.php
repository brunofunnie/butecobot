<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::Create()
    ->in(__DIR__);

$config = new Config();

$config
    ->setFinder($finder)
    ->setLineEnding(PHP_EOL) // Preserve existing line endings
    ->setRules([
        '@PSR12' => true, // Use PSR-12 coding standards
    ])
;

return $config;
