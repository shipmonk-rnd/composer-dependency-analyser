<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToScan(__FILE__, true)
    ->addPathToScan(__DIR__ . '/bin', false)
    ->addPathToExclude(__DIR__ . '/tests/data')
    ->ignoreErrorsOnExtensionsAndPaths(
        ['ext-dom', 'ext-libxml'],
        [__DIR__ . '/src/Result/JunitFormatter.php'], // optional usages guarded with extension_loaded()
        [ErrorType::DEV_DEPENDENCY_IN_PROD]
    );
