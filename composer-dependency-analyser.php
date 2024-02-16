<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToScan(__FILE__, true)
    ->addPathToScan(__DIR__ . '/bin', false)
    ->ignoreErrorsOnPath(__DIR__ . '/tests/data', [ErrorType::UNKNOWN_CLASS]);
