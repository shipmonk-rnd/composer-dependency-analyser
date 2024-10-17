<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Composer\Autoload\ClassLoader;
use LogicException;
use Phar;
use PHPStan\PharAutoloader;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function array_filter;
use function array_keys;
use function dirname;
use function file_exists;
use function ini_set;
use function realpath;
use function strtr;
use function unlink;

class AnalyserTest extends TestCase
{

    /**
     * @dataProvider provideConfigs
     * @param callable(Configuration): void $editConfig
     */
    public function test(callable $editConfig, AnalysisResult $expectedResult): void
    {
        $vendorDir = realpath(__DIR__ . '/data/autoloaded/vendor');
        self::assertNotFalse($vendorDir);
        $dependencies = [
            'regular/package' => false,
            'dev/package' => true,
            'regular/dead' => false,
            'dev/dead' => true,
        ];

        $config = new Configuration();
        $editConfig($config);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            $dependencies
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($expectedResult, $result);
    }

    /**
     * @return iterable<string, array{callable(Configuration): void, AnalysisResult}>
     */
    public function provideConfigs(): iterable
    {
        $variousUsagesPath = realpath(__DIR__ . '/data/not-autoloaded/analysis/various-usages.php');
        $unknownClassesPath = realpath(__DIR__ . '/data/not-autoloaded/analysis/unknown-classes.php');

        if ($unknownClassesPath === false || $variousUsagesPath === false) {
            throw new LogicException('Unable to realpath data files');
        }

        yield 'no paths' => [
            static function (Configuration $config): void {
            },
            $this->createAnalysisResult(0, [
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];
        yield 'no paths, report even unused dev' => [
            static function (Configuration $config): void {
                $config->enableAnalysisOfUnusedDevDependencies();
            },
            $this->createAnalysisResult(0, [
                ErrorType::UNUSED_DEPENDENCY => ['dev/dead', 'dev/package', 'regular/dead', 'regular/package'],
            ]),
        ];

        yield 'all paths excluded' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathsToExclude([$variousUsagesPath, $unknownClassesPath]);
            },
            $this->createAnalysisResult(0, [
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];

        yield 'no file extensions' => [
            static function (Configuration $config): void {
                $config->setFileExtensions([]);
            },
            $this->createAnalysisResult(0, [
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];

        yield 'default' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'default, report dev dead' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->enableAnalysisOfUnusedDevDependencies();
                $config->addPathToScan($variousUsagesPath, false);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['dev/dead', 'regular/dead'],
            ]),
        ];

        yield 'default, force use dead class' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addForceUsedSymbol('Regular\Dead\Clazz');
                $config->addPathToScan($variousUsagesPath, false);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
            ]),
        ];

        yield 'prod only in dev' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, true);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV => ['regular/package'],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'scan dir' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'scan more paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathsToScan([$variousUsagesPath, $unknownClassesPath], false);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'scan more paths, 2 calls' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->addPathToScan($unknownClassesPath, false);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)],
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)],
                ],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'scan dir, exclude path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathToExclude($unknownClassesPath);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'scan dir, exclude regex' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->addPathRegexToExclude('/unknown/');
                $config->addPathRegexesToExclude([
                    '/^not match$/',
                ]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore on path' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($variousUsagesPath), false);
                $config->ignoreErrorsOnPath($unknownClassesPath, [ErrorType::UNKNOWN_CLASS]);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore on path 2' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPath($variousUsagesPath, [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)],
                ],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore on paths' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan(dirname($unknownClassesPath), false);
                $config->ignoreErrorsOnPaths([$variousUsagesPath, $unknownClassesPath], [ErrorType::UNKNOWN_CLASS, ErrorType::SHADOW_DEPENDENCY, ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult(2, [
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ], [
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, $unknownClassesPath, null),
                new UnusedErrorIgnore(ErrorType::DEV_DEPENDENCY_IN_PROD, $unknownClassesPath, null),
            ]),
        ];

        yield 'ignore on package' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackages(['regular/dead'], [ErrorType::UNUSED_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
            ]),
        ];

        yield 'ignore on package 2' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackage('regular/dead', [ErrorType::UNUSED_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('shadow/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('dev/package', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
            ]),
        ];

        yield 'ignore on package and path' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrorsOnPackageAndPath('shadow/package', $variousUsagesPath, [ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore on package and path (overlapping with other ignores), all used' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPath($variousUsagesPath, [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('shadow/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackageAndPath('shadow/package', $variousUsagesPath, [ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore on package and path (overlapping with other ignores), one unused' => [
            static function (Configuration $config) use ($variousUsagesPath, $unknownClassesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPath($variousUsagesPath, [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('shadow/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackageAndPath('shadow/package', $unknownClassesPath, [ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ], [
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, $unknownClassesPath, 'shadow/package'),
            ]),
        ];

        yield 'ignore on package and path (overlapping with other ignores), all unused' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPath($unknownClassesPath, [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackage('invalid/package', [ErrorType::SHADOW_DEPENDENCY]);
                $config->ignoreErrorsOnPackageAndPath('invalid/package', $unknownClassesPath, [ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => [
                    'Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)],
                    'Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)],
                ],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ], [
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, $unknownClassesPath, null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, 'invalid/package'),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, $unknownClassesPath, 'invalid/package'),
            ]),
        ];

        yield 'ignore all unused' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNUSED_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
            ]),
        ];

        yield 'ignore all shadow' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore all dev-in-prod' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::DEV_DEPENDENCY_IN_PROD]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore all prod-only-in-dev' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, true);
                $config->ignoreErrors([ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Clazz' => [new SymbolUsage($variousUsagesPath, 11, SymbolKind::CLASSLIKE)]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore all unknown' => [
            static function (Configuration $config) use ($variousUsagesPath): void {
                $config->addPathToScan($variousUsagesPath, false);
                $config->ignoreErrors([ErrorType::UNKNOWN_CLASS]);
            },
            $this->createAnalysisResult(1, [
                ErrorType::DEV_DEPENDENCY_IN_PROD => ['dev/package' => ['Dev\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 16, SymbolKind::CLASSLIKE)]]],
                ErrorType::SHADOW_DEPENDENCY => ['shadow/package' => ['Shadow\Package\Clazz' => [new SymbolUsage($variousUsagesPath, 24, SymbolKind::CLASSLIKE)]]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead'],
            ]),
        ];

        yield 'ignore specific unknown class' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClasses(['Unknown\One']);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\Two' => [new SymbolUsage($unknownClassesPath, 4, SymbolKind::CLASSLIKE)]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];

        yield 'ignore unknown class by regex' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClassesRegex('~Two~');
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNKNOWN_CLASS => ['Unknown\One' => [new SymbolUsage($unknownClassesPath, 3, SymbolKind::CLASSLIKE)]],
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];

        yield 'ignore unknown classes multiple calls' => [
            static function (Configuration $config) use ($unknownClassesPath): void {
                $config->addPathToScan($unknownClassesPath, false);
                $config->ignoreUnknownClasses(['Unknown\One']);
                $config->ignoreUnknownClasses(['Unknown\Two']);
            },
            $this->createAnalysisResult(1, [
                ErrorType::UNUSED_DEPENDENCY => ['regular/dead', 'regular/package'],
            ]),
        ];
    }

    /**
     * @param array<ErrorType::*, array<mixed>> $args
     * @param list<UnusedErrorIgnore|UnusedSymbolIgnore> $unusedIgnores
     */
    private function createAnalysisResult(int $scannedFiles, array $args, array $unusedIgnores = []): AnalysisResult
    {
        return new AnalysisResult(
            $scannedFiles,
            0.0,
            [],
            array_filter($args[ErrorType::UNKNOWN_CLASS] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::UNKNOWN_FUNCTION] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::SHADOW_DEPENDENCY] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::DEV_DEPENDENCY_IN_PROD] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV] ?? []), // @phpstan-ignore-line ignore mixed
            array_filter($args[ErrorType::UNUSED_DEPENDENCY] ?? []), // @phpstan-ignore-line ignore mixed
            $unusedIgnores
        );
    }

    public function testNativeTypesNotReported(): void
    {
        $path = realpath(__DIR__ . '/data/not-autoloaded/builtin/native-symbols.php');
        self::assertNotFalse($path);

        $vendorDir = __DIR__;
        $config = new Configuration();
        $config->addPathToScan($path, false);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            []
        );
        $result = $detector->run();

        self::assertEquals($this->createAnalysisResult(1, [
            ErrorType::UNKNOWN_CLASS => [
                'resource' => [
                    new SymbolUsage($path, 34, SymbolKind::CLASSLIKE),
                    new SymbolUsage($path, 53, SymbolKind::CLASSLIKE),
                ],
            ],
        ]), $result);
    }

    public function testNoMultipleScansOfTheSameFile(): void
    {
        $path = realpath(__DIR__ . '/data/not-autoloaded/analysis/unknown-classes.php');
        self::assertNotFalse($path);

        $vendorDir = __DIR__ . '/data/autoloaded/vendor';

        $config = new Configuration();
        $config->addPathToScan($path, true);
        $config->addPathToScan($path, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            []
        );
        $result = $detector->run();

        self::assertEquals($this->createAnalysisResult(1, [
            ErrorType::UNKNOWN_CLASS => [
                'Unknown\One' => [new SymbolUsage($path, 3, SymbolKind::CLASSLIKE)],
                'Unknown\Two' => [new SymbolUsage($path, 4, SymbolKind::CLASSLIKE)],
            ],
        ]), $result);
    }

    public function testDevPathInsideProdPath(): void
    {
        $vendorDir = realpath(__DIR__ . '/data/autoloaded/vendor');
        $prodPath = realpath(__DIR__ . '/data/not-autoloaded/dev-in-subdirectory');
        $devPath = realpath(__DIR__ . '/data/not-autoloaded/dev-in-subdirectory/dev');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($prodPath);
        self::assertNotFalse($devPath);

        $config = new Configuration();
        $config->addPathToScan($prodPath, false);
        $config->addPathToScan($devPath, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'regular/package' => false,
                'dev/package' => true,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(2, []), $result);
    }

    public function testProdPathInsideDevPath(): void
    {
        $vendorDir = realpath(__DIR__ . '/data/autoloaded/vendor');
        $devPath = realpath(__DIR__ . '/data/not-autoloaded/prod-in-subdirectory');
        $prodPath = realpath(__DIR__ . '/data/not-autoloaded/prod-in-subdirectory/prod');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($devPath);
        self::assertNotFalse($prodPath);

        $config = new Configuration();
        $config->addPathToScan($prodPath, false);
        $config->addPathToScan($devPath, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'regular/package' => false,
                'dev/package' => true,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(2, []), $result);
    }

    public function testOtherSymbols(): void
    {
        require_once __DIR__ . '/data/not-autoloaded/other-symbols/symbol-declaration.php';

        $vendorDir = realpath(__DIR__ . '/../vendor');
        $path = realpath(__DIR__ . '/data/not-autoloaded/other-symbols/symbol-usages.php');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($path);

        $config = new Configuration();
        $config->addPathToScan($path, false);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            []
        );
        $result = $detector->run();

        self::assertEquals($this->createAnalysisResult(1, []), $result);
    }

    public function testExtensions(): void
    {
        $vendorDir = realpath(__DIR__ . '/../vendor');
        $prodPath = realpath(__DIR__ . '/data/not-autoloaded/extensions/ext-prod-usages.php');
        $devPath = realpath(__DIR__ . '/data/not-autoloaded/extensions/ext-dev-usages.php');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($prodPath);
        self::assertNotFalse($devPath);

        $config = new Configuration();
        $config->addPathToScan($prodPath, false);
        $config->addPathToScan($devPath, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'ext-dom' => false,
                'ext-libxml' => true,
                'ext-mbstring' => false,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(2, [
            ErrorType::SHADOW_DEPENDENCY => [
                'ext-pdo' => ['PDO' => [new SymbolUsage($prodPath, 5, SymbolKind::CLASSLIKE)]],
            ],
            ErrorType::DEV_DEPENDENCY_IN_PROD => [
                'ext-libxml' => ['LIBXML_NOEMPTYTAG' => [new SymbolUsage($prodPath, 3, SymbolKind::CONSTANT)]],
            ],
            ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV => [
                'ext-dom',
            ],
            ErrorType::UNUSED_DEPENDENCY => [
                'ext-mbstring',
            ],
        ]), $result);
    }

    public function testDisabledExtensionAnalysis(): void
    {
        $vendorDir = realpath(__DIR__ . '/../vendor');
        $prodPath = realpath(__DIR__ . '/data/not-autoloaded/extensions/ext-prod-usages.php');
        $devPath = realpath(__DIR__ . '/data/not-autoloaded/extensions/ext-dev-usages.php');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($prodPath);
        self::assertNotFalse($devPath);

        $config = new Configuration();
        $config->disableExtensionsAnalysis();
        $config->addPathToScan($prodPath, false);
        $config->addPathToScan($devPath, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'ext-dom' => false,
                'ext-libxml' => true,
                'ext-mbstring' => false,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(2, []), $result);
    }

    public function testPharSupport(): void
    {
        $canCreatePhar = ini_set('phar.readonly', '0');
        self::assertNotFalse($canCreatePhar, 'Your php runtime is not configured to allow phar creation. Use `php -dphar.readonly=0`');

        $pharPath = __DIR__ . '/data/not-autoloaded/phar/org/package/inner.phar';

        if (file_exists($pharPath)) {
            unlink($pharPath);
        }

        $phar = new Phar($pharPath);
        $phar->addFromString('index.php', '<?php namespace Phar { class Inner {} }');

        require_once $pharPath;

        $path = realpath(__DIR__ . '/data/not-autoloaded/phar/phar-usage.php');
        $vendorDir = realpath(__DIR__ . '/data/not-autoloaded/phar');
        self::assertNotFalse($path);
        self::assertNotFalse($vendorDir);

        $config = new Configuration();
        $config->addPathToScan($path, false);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'org/package' => false,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(1, []), $result);
    }

    /**
     * @runInSeparateProcess It alters composer's autoloader, lets not affect others
     */
    public function testMultipleClassloaders(): void
    {
        $path = realpath(__DIR__ . '/data/not-autoloaded/multiple-classloaders/phpstan-rule.php');
        self::assertNotFalse($path);

        $vendorDir = realpath(__DIR__ . '/../vendor');
        self::assertNotFalse($vendorDir);

        $classLoaders = ClassLoader::getRegisteredLoaders();
        self::assertSame([$vendorDir], array_keys($classLoaders));

        // @phpstan-ignore-next-line Ignore BC promise
        PharAutoloader::loadClass('_PHPStan_'); // causes PHPStan's autoloader to be registered

        $classLoaders = ClassLoader::getRegisteredLoaders();
        self::assertSame([
            strtr('phar://' . $vendorDir . '/phpstan/phpstan/phpstan.phar/vendor', '\\', '/'), // no backslashes even on Windows
            $vendorDir,
        ], array_keys($classLoaders));

        $config = new Configuration();
        $config->addPathToScan($path, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            $classLoaders,
            $config,
            [
                'phpstan/phpstan' => true,
            ]
        );
        $result = $detector->run();

        // nikic/php-parser not reported as shadow dependency as it exists in the PHPStan's vendor
        $this->assertResultsWithoutUsages($this->createAnalysisResult(1, []), $result);
    }

    public function testFunctions(): void
    {
        $path = __DIR__ . '/data/not-autoloaded/functions/org/package/fn-def.php';
        require_once $path;

        $vendorDir = realpath(__DIR__ . '/data/not-autoloaded/functions');
        $path = realpath(__DIR__ . '/data/not-autoloaded/functions/fn-usage.php');
        self::assertNotFalse($vendorDir);
        self::assertNotFalse($path);

        $config = new Configuration();
        $config->addPathToScan($path, false);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            ['org/package' => false]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(1, []), $result);
    }

    public function testExplicitFileWithoutExtension(): void
    {
        $path = realpath(__DIR__ . '/data/not-autoloaded/file-without-extension/script');
        $vendorDir = realpath(__DIR__ . '/data/autoloaded/vendor');
        self::assertNotFalse($path);
        self::assertNotFalse($vendorDir);

        $config = new Configuration();
        $config->addPathToScan($path, true);

        $detector = new Analyser(
            $this->getStopwatchMock(),
            $vendorDir,
            [$vendorDir => $this->getClassLoaderMock()],
            $config,
            [
                'dev/package' => true,
            ]
        );
        $result = $detector->run();

        $this->assertResultsWithoutUsages($this->createAnalysisResult(1, []), $result);
    }

    private function getStopwatchMock(): Stopwatch
    {
        $stopwatch = $this->createMock(Stopwatch::class);
        $stopwatch->expects(self::once())
            ->method('stop')
            ->willReturn(0.0);

        return $stopwatch;
    }

    private function getClassLoaderMock(): ClassLoader
    {
        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->expects(self::any())
            ->method('findFile')
            ->willReturn(false);

        return $classLoader;
    }

    private function assertResultsWithoutUsages(AnalysisResult $expectedResult, AnalysisResult $result): void
    {
        self::assertSame($expectedResult->getScannedFilesCount(), $result->getScannedFilesCount(), 'Scanned files count mismatch');
        self::assertEquals($expectedResult->getUnusedIgnores(), $result->getUnusedIgnores(), 'Unused ignores mismatch');
        self::assertEquals($expectedResult->getUnknownClassErrors(), $result->getUnknownClassErrors(), 'Unknown class mismatch');
        self::assertEquals($expectedResult->getUnknownFunctionErrors(), $result->getUnknownFunctionErrors(), 'Unknown functions mismatch');
        self::assertEquals($expectedResult->getShadowDependencyErrors(), $result->getShadowDependencyErrors(), 'Shadow dependency mismatch');
        self::assertEquals($expectedResult->getDevDependencyInProductionErrors(), $result->getDevDependencyInProductionErrors(), 'Dev dependency in production mismatch');
        self::assertEquals($expectedResult->getProdDependencyOnlyInDevErrors(), $result->getProdDependencyOnlyInDevErrors(), 'Prod dependency only in dev mismatch');
        self::assertEquals($expectedResult->getUnusedDependencyErrors(), $result->getUnusedDependencyErrors(), 'Unused dependency mismatch');
    }

}
