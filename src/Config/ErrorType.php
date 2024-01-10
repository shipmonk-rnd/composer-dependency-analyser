<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

final class ErrorType
{

    public const UNKNOWN_CLASS = 'unknown-class';
    public const SHADOW_DEPENDENCY = 'shadow-dependency';
    public const UNUSED_DEPENDENCY = 'unused-dependency';
    public const DEV_DEPENDENCY_IN_PROD = 'dev-dependency-in-prod';
    public const PROD_DEPENDENCY_ONLY_IN_DEV = 'prod-dependency-only-in-dev';

}
