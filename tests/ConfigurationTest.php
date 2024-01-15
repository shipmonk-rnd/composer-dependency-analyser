<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use const DIRECTORY_SEPARATOR;

class ConfigurationTest extends TestCase
{

    public function testPathsWithIgnore(): void
    {
        $configuration = new Configuration();
        $configuration->ignoreErrorsOnPath(__DIR__ . '/app/../', [ErrorType::SHADOW_DEPENDENCY]);
        $configuration->ignoreErrorsOnPackageAndPath('vendor/package', __DIR__ . '/../tests/app', [ErrorType::SHADOW_DEPENDENCY]);

        self::assertEquals(
            [
                __DIR__,
                __DIR__ . DIRECTORY_SEPARATOR . 'app',
            ],
            $configuration->getPathsWithIgnore()
        );
    }

}
