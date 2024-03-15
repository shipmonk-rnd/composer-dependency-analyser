<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->addPathToScan(__FILE__, true)
    ->addPathToScan(__DIR__ . '/bin', false)
    ->addPathToExclude(__DIR__ . '/tests/data');
