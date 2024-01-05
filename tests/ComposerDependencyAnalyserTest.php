<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Config\Configuration;
use ShipMonk\Composer\Crate\ClassUsage;
use ShipMonk\Composer\Enum\ErrorType;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\SymbolError;
use ShipMonk\Composer\Error\UnusedDependencyError;
use function dirname;

class ComposerDependencyAnalyserTest extends TestCase
{

    /**
     * @dataProvider provideConfigs
     * @param callable(Configuration): void $editConfig
     * @param list<SymbolError> $expectedResult
     */
    public function test(callable $editConfig, array $expectedResult): void
    {
        $appDir = __DIR__ . '/app';
        $vendorDir = __DIR__ . '/vendor';
        $classmap = [
            'Regular\Package\Clazz' => $vendorDir . '/regular/package/Clazz.php',
            'Shadow\Package\Clazz' => $vendorDir . '/shadow/package/Clazz.php',
            'Dev\Package\Clazz' => $vendorDir . '/dev/package/Clazz.php',
            'App\Clazz' => $appDir . '/Clazz.php',
        ];
        $dependencies = [
            'regular/package' => false,
            'dev/package' => true,
            'regular/dead' => false,
            'dev/dead' => true,
        ];

        $config = new Configuration();
        $editConfig($config);

        $detector = new ComposerDependencyAnalyser(
            $config,
            $vendorDir,
            $classmap,
            $dependencies
        );
        $result = $detector->run();

        self::assertEquals($expectedResult, $result);
    }

    /**
     * @return iterable<string, array{callable(Configuration): void, list<SymbolError>}>
     */
    public function provideConfigs(): iterable
    {
        $variousUsagesPath = __DIR__ . '/data/analysis/various-usages.php';
        $unknownClassesPath = __DIR__ . '/data/analysis/unknown-classes.php';

        yield 'no paths' => [
            static function (Configuration $config): void {
            },
            [
                new UnusedDependencyError('regular/dead'),
                new UnusedDependencyError('regular/package'),
            ]
        ];

        yield 'all paths excluded' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathsToExclude([$variousUsagesPath, $unknownClassesPath]);
            },
            [
                new UnusedDependencyError('regular/dead'),
                new UnusedDependencyError('regular/package'),
            ]
        ];

        yield 'no file extensions' => [
            static function (Configuration $config): void {
                $config->setFileExtensions([]);
            },
            [
                new UnusedDependencyError('regular/dead'),
                new UnusedDependencyError('regular/package'),
            ]
        ];

        yield 'default' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'scan dir' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\One', $unknownClassesPath, 3)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Two', $unknownClassesPath, 4)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'scan more paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathsToScan([$variousUsagesPath, $unknownClassesPath], false);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\One', $unknownClassesPath, 3)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Two', $unknownClassesPath, 4)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'scan more paths, 2 calls' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->addPathToScan($unknownClassesPath, false);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\One', $unknownClassesPath, 3)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Two', $unknownClassesPath, 4)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'scan dir, exclude path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathToExclude($unknownClassesPath);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore on path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->ignoreErrorsOnPath($unknownClassesPath, [ErrorType::UNKNOWN_CLASS]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore on path 2' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPath($variousUsagesPath, [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\One', $unknownClassesPath, 3)),
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Two', $unknownClassesPath, 4)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore on paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPaths([$variousUsagesPath, $unknownClassesPath], [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            [
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore on package' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackage('regular/dead', [ErrorType::UNUSED_DEPENDENCY]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
            ]
        ];

        yield 'ignore on package 2' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackage('regular/dead', [ErrorType::UNUSED_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('shadow/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('dev/package', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
            ]
        ];

        yield 'ignore all unused' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNUSED_DEPENDENCY]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
            ]
        ];

        yield 'ignore all shadow' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore all dev-in-prod' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $variousUsagesPath, 11)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore all unknown' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNKNOWN_CLASS]);
            },
            [
                new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $variousUsagesPath, 16)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $variousUsagesPath, 24)),
                new UnusedDependencyError('regular/dead'),
            ]
        ];

        yield 'ignore specific unknown class' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClasses(['Unknown\One']);
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\Two', $unknownClassesPath, 4)),
                new UnusedDependencyError('regular/dead'),
                new UnusedDependencyError('regular/package'),
            ]
        ];

        yield 'ignore unknown class by regex' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClassesRegex('~Two~');
            },
            [
                new ClassmapEntryMissingError(new ClassUsage('Unknown\One', $unknownClassesPath, 3)),
                new UnusedDependencyError('regular/dead'),
                new UnusedDependencyError('regular/package'),
            ]
        ];
    }

}
