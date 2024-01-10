<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use LogicException;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function array_filter;
use function dirname;
use function realpath;

class ComposerDependencyAnalyserTest extends TestCase
{

    /**
     * @dataProvider provideConfigs
     * @param callable(Configuration): void $editConfig
     */
    public function test(callable $editConfig, AnalysisResult $expectedResult): void
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
     * @return iterable<string, array{callable(Configuration): void, AnalysisResult}>
     */
    public function provideConfigs(): iterable
    {
        $variousUsagesPath = realpath(__DIR__ . '/data/analysis/various-usages.php');
        $unknownClassesPath = realpath(__DIR__ . '/data/analysis/unknown-classes.php');

        if ($unknownClassesPath === false || $variousUsagesPath === false) {
            throw new LogicException('Unable to realpath data files');
        }

        yield 'no paths' => [
            static function (Configuration $config): void {
            },
            $this->createAnalysisResult([
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ])
        ];

        yield 'all paths excluded' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathsToExclude([$variousUsagesPath, $unknownClassesPath]);
            },
            $this->createAnalysisResult([
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ])
        ];

        yield 'no file extensions' => [
            static function (Configuration $config): void {
                $config->setFileExtensions([]);
            },
            $this->createAnalysisResult([
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ])
        ];

        yield 'default' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'scan dir' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'scan more paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathsToScan([$variousUsagesPath, $unknownClassesPath], false);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'scan more paths, 2 calls' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->addPathToScan($unknownClassesPath, false);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'scan dir, exclude path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathToExclude($unknownClassesPath);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'ignore on path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->ignoreErrorsOnPath($unknownClassesPath, [ErrorType::UNKNOWN_CLASS]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'ignore on path 2' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPath($variousUsagesPath, [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4)],
                ],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ])
        ];

        yield 'ignore on paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPaths([$variousUsagesPath, $unknownClassesPath], [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult([
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ])
        ];

        yield 'ignore on package' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackages(['regular/dead'], [ErrorType::UNUSED_DEPENDENCY]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
            ])
        ];

        yield 'ignore on package 2' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackage('regular/dead', [ErrorType::UNUSED_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('shadow/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('dev/package', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
            ])
        ];

        yield 'ignore all unused' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNUSED_DEPENDENCY]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
            ])
        ];

        yield 'ignore all shadow' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'ignore all dev-in-prod' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11)]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ])
        ];

        yield 'ignore all unknown' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNKNOWN_CLASS]);
            },
            $this->createAnalysisResult([
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead']
            ])
        ];

        yield 'ignore specific unknown class' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClasses(['Unknown\One']);
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4)]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ])
        ];

        yield 'ignore unknown class by regex' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClassesRegex('~Two~');
            },
            $this->createAnalysisResult([
                ErrorType::UNKNOWN_CLASS => ['Unknown\One' => [new SymbolUsage($unknownClassesPath, 3)]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ])
        ];
    }

    /**
     * @param array<ErrorType::*, array<mixed>> $args
     */
    private function createAnalysisResult(array $args): AnalysisResult
    {
        return new AnalysisResult(
            array_filter($args[ErrorType::UNKNOWN_CLASS] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::SHADOW_DEPENDENCY] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::DEV_DEPENDENCY_IN_PROD] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::UNUSED_DEPENDENCY] ?? []) // @phpstan-ignore-line ignore mixed
        );
    }

}
