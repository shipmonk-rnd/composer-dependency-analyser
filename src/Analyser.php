<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Composer\Autoload\ClassLoader;
use DirectoryIterator;
use Generator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\PathToScan;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use UnexpectedValueException;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function explode;
use function file_get_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
use function in_array;
use function is_file;
use function is_string;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function usort;
use const DIRECTORY_SEPARATOR;

class Analyser
{

    /**
     * Those are core PHP extensions, that can never be disabled
     * There are more PHP "core" extensions, that are bundled by default, but PHP can be compiled without them
     * You can check which are added conditionally in https://github.com/php/php-src/tree/master/ext (see config.w32 files)
     */
    private const CORE_EXTENSIONS = [
        'ext-core',
        'ext-date',
        'ext-json',
        'ext-hash',
        'ext-pcre',
        'ext-phar',
        'ext-reflection',
        'ext-spl',
        'ext-random',
        'ext-standard',
    ];

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @var list<string>
     */
    private $vendorDirs;

    /**
     * @var list<ClassLoader>
     */
    private $classLoaders;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * className => path
     *
     * @var array<string, ?string>
     */
    private $classmap = [];

    /**
     * package or ext-* => is dev dependency
     *
     * @var array<string, bool>
     */
    private $composerJsonDependencies;

    /**
     * symbol name => true
     *
     * @var array<string, true>
     */
    private $ignoredSymbols;

    /**
     * custom function name => path
     *
     * @var array<string, string>
     */
    private $definedFunctions = [];

    /**
     * kind => [symbol name => ext-*]
     *
     * @var array<SymbolKind::*, array<string, string>>
     */
    private $extensionSymbols;

    /**
     * @param array<string, ClassLoader> $classLoaders vendorDir => ClassLoader (e.g. result of \Composer\Autoload\ClassLoader::getRegisteredLoaders())
     * @param array<string, bool> $composerJsonDependencies package or ext-* => is dev dependency
     */
    public function __construct(
        Stopwatch $stopwatch,
        string $defaultVendorDir,
        array $classLoaders,
        Configuration $config,
        array $composerJsonDependencies
    )
    {
        $this->stopwatch = $stopwatch;
        $this->config = $config;
        $this->composerJsonDependencies = $composerJsonDependencies;
        $this->vendorDirs = array_keys($classLoaders + [$defaultVendorDir => null]);
        $this->classLoaders = array_values($classLoaders);

        $this->initExistingSymbols();
    }

    /**
     * @throws InvalidPathException
     */
    public function run(): AnalysisResult
    {
        $this->stopwatch->start();

        $scannedFilesCount = 0;
        $unknownClassErrors = [];
        $unknownFunctionErrors = [];
        $shadowErrors = [];
        $devInProdErrors = [];
        $prodOnlyInDevErrors = [];
        $unusedErrors = [];

        $usedDependencies = [];
        $prodPackagesUsedInProdPath = [];

        $usages = [];

        $ignoreList = $this->config->getIgnoreList();

        foreach ($this->getUniqueFilePathsToScan() as $filePath => $isDevFilePath) {
            $scannedFilesCount++;

            $usedSymbolsByKind = $this->getUsedSymbolsInFile($filePath);

            foreach ($usedSymbolsByKind as $kind => $usedSymbols) {
                foreach ($usedSymbols as $usedSymbol => $lineNumbers) {
                    $usedSymbolNameForIgnoreCheck = $kind === SymbolKind::FUNCTION ? strtolower($usedSymbol) : $usedSymbol;

                    if (isset($this->ignoredSymbols[$usedSymbolNameForIgnoreCheck])) {
                        continue;
                    }

                    if (isset($this->extensionSymbols[$kind][$usedSymbol])) {
                        $dependencyName = $this->extensionSymbols[$kind][$usedSymbol];

                    } else {
                        $symbolPath = $this->getSymbolPath($usedSymbol, $kind);

                        if ($symbolPath === null) {
                            if ($kind === SymbolKind::CLASSLIKE && !$ignoreList->shouldIgnoreUnknownClass($usedSymbol, $filePath)) {
                                foreach ($lineNumbers as $lineNumber) {
                                    $unknownClassErrors[$usedSymbol][] = new SymbolUsage($filePath, $lineNumber, $kind);
                                }
                            }

                            if ($kind === SymbolKind::FUNCTION && !$ignoreList->shouldIgnoreUnknownFunction($usedSymbol, $filePath)) {
                                foreach ($lineNumbers as $lineNumber) {
                                    $unknownFunctionErrors[$usedSymbol][] = new SymbolUsage($filePath, $lineNumber, $kind);
                                }
                            }

                            continue;
                        }

                        if (!$this->isVendorPath($symbolPath)) {
                            continue; // local class
                        }

                        $dependencyName = $this->getPackageNameFromVendorPath($symbolPath);
                    }

                    if (
                        $this->isShadowDependency($dependencyName)
                        && !$ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, $filePath, $dependencyName)
                    ) {
                        foreach ($lineNumbers as $lineNumber) {
                            $shadowErrors[$dependencyName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber, $kind);
                        }
                    }

                    if (
                        !$isDevFilePath
                        && $this->isDevDependency($dependencyName)
                        && !$ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, $filePath, $dependencyName)
                    ) {
                        foreach ($lineNumbers as $lineNumber) {
                            $devInProdErrors[$dependencyName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber, $kind);
                        }
                    }

                    if (
                        !$isDevFilePath
                        && !$this->isDevDependency($dependencyName)
                    ) {
                        $prodPackagesUsedInProdPath[$dependencyName] = true;
                    }

                    $usedDependencies[$dependencyName] = true;

                    foreach ($lineNumbers as $lineNumber) {
                        $usages[$dependencyName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber, $kind);
                    }
                }
            }
        }

        $forceUsedPackages = [];

        foreach ($this->config->getForceUsedSymbols() as $forceUsedSymbol) {
            if (isset($this->ignoredSymbols[$forceUsedSymbol])) {
                continue;
            }

            if (
                isset($this->extensionSymbols[SymbolKind::FUNCTION][$forceUsedSymbol])
                || isset($this->extensionSymbols[SymbolKind::CONSTANT][$forceUsedSymbol])
                || isset($this->extensionSymbols[SymbolKind::CLASSLIKE][$forceUsedSymbol])
            ) {
                $forceUsedDependency = $this->extensionSymbols[SymbolKind::FUNCTION][$forceUsedSymbol]
                    ?? $this->extensionSymbols[SymbolKind::CONSTANT][$forceUsedSymbol]
                    ?? $this->extensionSymbols[SymbolKind::CLASSLIKE][$forceUsedSymbol];
            } else {
                $symbolPath = $this->getSymbolPath($forceUsedSymbol, null);

                if ($symbolPath === null || !$this->isVendorPath($symbolPath)) {
                    continue;
                }

                $forceUsedDependency = $this->getPackageNameFromVendorPath($symbolPath);
            }

            $usedDependencies[$forceUsedDependency] = true;
            $forceUsedPackages[$forceUsedDependency] = true;
        }

        if ($this->config->shouldReportUnusedDevDependencies()) {
            $dependenciesForUnusedAnalysis = array_keys($this->composerJsonDependencies);

        } else {
            $dependenciesForUnusedAnalysis = array_keys(array_filter($this->composerJsonDependencies, static function (bool $devDependency) {
                return !$devDependency; // dev deps are typically used only in CI
            }));
        }

        $unusedDependencies = array_diff(
            $dependenciesForUnusedAnalysis,
            array_keys($usedDependencies),
            self::CORE_EXTENSIONS
        );

        foreach ($unusedDependencies as $unusedDependency) {
            if (!$ignoreList->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, null, $unusedDependency)) {
                $unusedErrors[] = $unusedDependency;
            }
        }

        $prodDependencies = array_keys(array_filter($this->composerJsonDependencies, static function (bool $devDependency) {
            return !$devDependency;
        }));
        $prodPackagesUsedOnlyInDev = array_diff(
            $prodDependencies,
            array_keys($prodPackagesUsedInProdPath),
            array_keys($forceUsedPackages), // we dont know where are those used, lets not report them
            $unusedDependencies,
            self::CORE_EXTENSIONS
        );

        foreach ($prodPackagesUsedOnlyInDev as $prodPackageUsedOnlyInDev) {
            if (!$ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, null, $prodPackageUsedOnlyInDev)) {
                $prodOnlyInDevErrors[] = $prodPackageUsedOnlyInDev;
            }
        }

        return new AnalysisResult(
            $scannedFilesCount,
            $this->stopwatch->stop(),
            $usages,
            $unknownClassErrors,
            $unknownFunctionErrors,
            $shadowErrors,
            $devInProdErrors,
            $prodOnlyInDevErrors,
            $unusedErrors,
            $ignoreList->getUnusedIgnores()
        );
    }

    /**
     * What paths overlap in composer.json autoload sections,
     * we don't want to scan paths multiple times
     *
     * @return array<string, bool>
     * @throws InvalidPathException
     */
    private function getUniqueFilePathsToScan(): array
    {
        $allFilePaths = [];

        $scanPaths = $this->config->getPathsToScan();
        usort($scanPaths, static function (PathToScan $a, PathToScan $b): int {
            return strlen($a->getPath()) <=> strlen($b->getPath());
        });

        foreach ($scanPaths as $scanPath) {
            foreach ($this->listPhpFilesIn($scanPath->getPath()) as $filePath) {
                if ($this->config->isExcludedFilepath($filePath)) {
                    continue;
                }

                $allFilePaths[$filePath] = $scanPath->isDev();
            }
        }

        return $allFilePaths;
    }

    private function isShadowDependency(string $packageName): bool
    {
        return !isset($this->composerJsonDependencies[$packageName]);
    }

    private function isDevDependency(string $packageName): bool
    {
        $isDevDependency = $this->composerJsonDependencies[$packageName] ?? null;
        return $isDevDependency === true;
    }

    private function getPackageNameFromVendorPath(string $realPath): string
    {
        foreach ($this->vendorDirs as $vendorDir) {
            if (strpos($realPath, $vendorDir) === 0) {
                $filePathInVendor = trim(str_replace($vendorDir, '', $realPath), DIRECTORY_SEPARATOR);
                [$vendor, $package] = explode(DIRECTORY_SEPARATOR, $filePathInVendor, 3);
                return "$vendor/$package";
            }
        }

        throw new LogicException("Path '$realPath' not found in vendor. This method can be called only when isVendorPath(\$realPath) returns true");
    }

    /**
     * @return array<SymbolKind::*, array<string, list<int>>>
     * @throws InvalidPathException
     */
    private function getUsedSymbolsInFile(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new InvalidPathException("Unable to get contents of '$filePath'");
        }

        return (new UsedSymbolExtractor($code))->parseUsedSymbols(
            array_keys($this->extensionSymbols[SymbolKind::CLASSLIKE]),
            array_keys($this->extensionSymbols[SymbolKind::FUNCTION]),
            array_keys($this->extensionSymbols[SymbolKind::CONSTANT])
        );
    }

    /**
     * @return Generator<string>
     * @throws InvalidPathException
     */
    private function listPhpFilesIn(string $path): Generator
    {
        if (is_file($path)) {
            yield $path;
            return;
        }

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        } catch (UnexpectedValueException $e) {
            throw new InvalidPathException("Unable to list files in $path", $e);
        }

        foreach ($iterator as $entry) {
            /** @var DirectoryIterator $entry */
            if (!$entry->isFile() || !in_array($entry->getExtension(), $this->config->getFileExtensions(), true)) {
                continue;
            }

            yield $entry->getPathname();
        }
    }

    private function isVendorPath(string $realPath): bool
    {
        foreach ($this->vendorDirs as $vendorDir) {
            if (strpos($realPath, $vendorDir) === 0) {
                return true;
            }
        }

        return false;
    }

    private function getSymbolPath(string $symbol, ?int $kind): ?string
    {
        if ($kind === SymbolKind::FUNCTION || $kind === null) {
            $lowerSymbol = strtolower($symbol);

            if (isset($this->definedFunctions[$lowerSymbol])) {
                return $this->definedFunctions[$lowerSymbol];
            }

            if ($kind === SymbolKind::FUNCTION) {
                return null;
            }
        }

        if (!array_key_exists($symbol, $this->classmap)) {
            $path = $this->detectFileByClassLoader($symbol) ?? $this->detectFileByReflection($symbol);
            $this->classmap[$symbol] = $path === null
                ? null
                : $this->normalizePath($path); // composer ClassLoader::findFile() returns e.g. /opt/project/vendor/composer/../../src/Config/Configuration.php (which is not vendor path)
        }

        return $this->classmap[$symbol];
    }

    /**
     * This should minimize the amount autoloaded classes
     */
    private function detectFileByClassLoader(string $usedSymbol): ?string
    {
        foreach ($this->classLoaders as $classLoader) {
            $filePath = $classLoader->findFile($usedSymbol);

            if ($filePath !== false) {
                return $filePath;
            }
        }

        return null;
    }

    private function detectFileByReflection(string $usedSymbol): ?string
    {
        try {
            $reflection = new ReflectionClass($usedSymbol); // @phpstan-ignore-line ignore not a class-string, we catch the exception
        } catch (ReflectionException $e) {
            return null; // not autoloadable class
        }

        $filePath = $reflection->getFileName();

        if ($filePath === false) {
            return null; // should probably never happen as internal classes are handled earlier
        }

        return $filePath;
    }

    private function normalizePath(string $filePath): string
    {
        $pharPrefix = 'phar://';

        if (strpos($filePath, $pharPrefix) === 0) {
            /** @var string $filePath Cannot resolve to false */
            $filePath = substr($filePath, strlen($pharPrefix));
        }

        return Path::normalize($filePath);
    }

    private function initExistingSymbols(): void
    {
        $this->ignoredSymbols = [
            // built-in types
            'bool' => true,
            'int' => true,
            'float' => true,
            'string' => true,
            'null' => true,
            'array' => true,
            'object' => true,
            'never' => true,
            'void' => true,

            // value types
            'false' => true,
            'true' => true,

            // callable
            'callable' => true,

            // relative class types
            'self' => true,
            'parent' => true,
            'static' => true,

            // aliases
            'mixed' => true,
            'iterable' => true,

            // composer internal classes
            'Composer\\InstalledVersions' => true,
            'Composer\\Autoload\\ClassLoader' => true,
        ];

        /** @var array<string, array<string, mixed>> $definedConstants */
        $definedConstants = get_defined_constants(true);

        foreach ($definedConstants as $constantExtension => $constants) {
            foreach ($constants as $constantName => $_) {
                if ($constantExtension === 'user') {
                    $this->ignoredSymbols[$constantName] = true;
                } else {
                    $extensionName = $this->getNormalizedExtensionName($constantExtension);

                    if (in_array($extensionName, self::CORE_EXTENSIONS, true)) {
                        $this->ignoredSymbols[$constantName] = true;
                    } else {
                        $this->extensionSymbols[SymbolKind::CONSTANT][$constantName] = $extensionName;
                    }
                }
            }
        }

        foreach (get_defined_functions() as $functionNames) {
            foreach ($functionNames as $functionName) {
                $reflectionFunction = new ReflectionFunction($functionName);
                $functionFilePath = $reflectionFunction->getFileName();

                if ($reflectionFunction->getExtension() === null) {
                    if (is_string($functionFilePath)) {
                        $this->definedFunctions[$functionName] = Path::normalize($functionFilePath);
                    }
                } else {
                    $extensionName = $this->getNormalizedExtensionName($reflectionFunction->getExtension()->name);

                    if (in_array($extensionName, self::CORE_EXTENSIONS, true)) {
                        $this->ignoredSymbols[$functionName] = true;
                    } else {
                        $this->extensionSymbols[SymbolKind::FUNCTION][$functionName] = $extensionName;
                    }
                }
            }
        }

        $classLikes = [
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits(),
        ];

        foreach ($classLikes as $classLikeNames) {
            foreach ($classLikeNames as $classLikeName) {
                $classReflection = new ReflectionClass($classLikeName);

                if ($classReflection->getExtension() !== null) {
                    $extensionName = $this->getNormalizedExtensionName($classReflection->getExtension()->name);

                    if (in_array($extensionName, self::CORE_EXTENSIONS, true)) {
                        $this->ignoredSymbols[$classLikeName] = true;
                    } else {
                        $this->extensionSymbols[SymbolKind::CLASSLIKE][$classLikeName] = $extensionName;
                    }
                }
            }
        }
    }

    private function getNormalizedExtensionName(string $extension): string
    {
        return 'ext-' . ComposerJson::normalizeExtensionName($extension);
    }

}
