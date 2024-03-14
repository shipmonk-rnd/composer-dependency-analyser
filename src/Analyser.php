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
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use UnexpectedValueException;
use function array_change_key_case;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function end;
use function explode;
use function file_get_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
use function implode;
use function in_array;
use function is_file;
use function preg_split;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const CASE_LOWER;
use const DIRECTORY_SEPARATOR;

class Analyser
{

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * vendorDir => ClassLoader
     *
     * @var array<string, ClassLoader>
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
     * package name => is dev dependency
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
     * @param array<string, ClassLoader> $classLoaders vendorDir => ClassLoader (e.g. result of \Composer\Autoload\ClassLoader::getRegisteredLoaders())
     * @param array<string, bool> $composerJsonDependencies package name => is dev dependency
     */
    public function __construct(
        Stopwatch $stopwatch,
        array $classLoaders,
        Configuration $config,
        array $composerJsonDependencies
    )
    {
        $this->stopwatch = $stopwatch;
        $this->config = $config;
        $this->composerJsonDependencies = $composerJsonDependencies;
        $this->ignoredSymbols = $this->getIgnoredSymbols();

        foreach ($classLoaders as $vendorDir => $classLoader) {
            $this->classLoaders[$vendorDir] = $classLoader;
        }
    }

    /**
     * @throws InvalidPathException
     */
    public function run(): AnalysisResult
    {
        $this->stopwatch->start();

        $scannedFilesCount = 0;
        $classmapErrors = [];
        $shadowErrors = [];
        $devInProdErrors = [];
        $prodOnlyInDevErrors = [];
        $unusedErrors = [];

        $usedPackages = [];
        $prodPackagesUsedInProdPath = [];

        $usages = [];

        $ignoreList = $this->config->getIgnoreList();

        foreach ($this->getUniqueFilePathsToScan() as $filePath => $isDevFilePath) {
            $scannedFilesCount++;

            foreach ($this->getUsedSymbolsInFile($filePath) as $usedSymbol => $lineNumbers) {
                if (isset($this->ignoredSymbols[strtolower($usedSymbol)])) {
                    continue;
                }

                $symbolPath = $this->getSymbolPath($usedSymbol);

                if ($symbolPath === null) {
                    if (!$ignoreList->shouldIgnoreUnknownClass($usedSymbol, $filePath)) {
                        foreach ($lineNumbers as $lineNumber) {
                            $classmapErrors[$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                        }
                    }

                    continue;
                }

                if (!$this->isVendorPath($symbolPath)) {
                    continue; // local class
                }

                $packageName = $this->getPackageNameFromVendorPath($symbolPath);

                if (
                    $this->isShadowDependency($packageName)
                    && !$ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, $filePath, $packageName)
                ) {
                    foreach ($lineNumbers as $lineNumber) {
                        $shadowErrors[$packageName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                    }
                }

                if (
                    !$isDevFilePath
                    && $this->isDevDependency($packageName)
                    && !$ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, $filePath, $packageName)
                ) {
                    foreach ($lineNumbers as $lineNumber) {
                        $devInProdErrors[$packageName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                    }
                }

                if (
                    !$isDevFilePath
                    && !$this->isDevDependency($packageName)
                ) {
                    $prodPackagesUsedInProdPath[$packageName] = true;
                }

                $usedPackages[$packageName] = true;

                foreach ($lineNumbers as $lineNumber) {
                    $usages[$packageName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                }
            }
        }

        $forceUsedPackages = [];

        foreach ($this->config->getForceUsedSymbols() as $forceUsedSymbol) {
            if (isset($this->ignoredSymbols[$forceUsedSymbol])) {
                continue;
            }

            $symbolPath = $this->getSymbolPath($forceUsedSymbol);

            if ($symbolPath === null || !$this->isVendorPath($symbolPath)) {
                continue;
            }

            $forceUsedPackage = $this->getPackageNameFromVendorPath($symbolPath);
            $usedPackages[$forceUsedPackage] = true;
            $forceUsedPackages[$forceUsedPackage] = true;
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
            array_keys($usedPackages)
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
            $unusedDependencies
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
            $classmapErrors,
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

        foreach ($this->config->getPathsToScan() as $scanPath) {
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
        foreach ($this->classLoaders as $vendorDir => $_) {
            if (strpos($realPath, $vendorDir) === 0) {
                $filePathInVendor = trim(str_replace($vendorDir, '', $realPath), DIRECTORY_SEPARATOR);
                [$vendor, $package] = explode(DIRECTORY_SEPARATOR, $filePathInVendor, 3);
                return "$vendor/$package";
            }
        }

        throw new LogicException("Path '$realPath' not found in vendor. This method can be called only when isVendorPath(\$realPath) returns true");
    }

    /**
     * @return array<string, list<int>>
     * @throws InvalidPathException
     */
    private function getUsedSymbolsInFile(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new InvalidPathException("Unable to get contents of '$filePath'");
        }

        return (new UsedSymbolExtractor($code))->parseUsedClasses();
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
            throw new InvalidPathException("Unable to list files in $path", 0, $e);
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
        foreach ($this->classLoaders as $vendorDir => $_) {
            if (strpos($realPath, $vendorDir) === 0) {
                return true;
            }
        }

        return false;
    }

    private function getSymbolPath(string $symbol): ?string
    {
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

        return $this->doNormalizePath($filePath);
    }

    /**
     * Based on Nette\Utils\FileSystem::normalizePath
     *
     * @license https://github.com/nette/utils/blob/v4.0.4/license.md
     */
    private function doNormalizePath(string $path): string
    {
        /** @var list<string> $parts */
        $parts = $path === ''
            ? []
            : preg_split('~[/\\\\]+~', $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '..' && $result !== [] && end($result) !== '..' && end($result) !== '') {
                array_pop($result);
            } elseif ($part !== '.') {
                $result[] = $part;
            }
        }

        return $result === ['']
            ? DIRECTORY_SEPARATOR
            : implode(DIRECTORY_SEPARATOR, $result);
    }

    /**
     * @return array<string, true>
     */
    private function getIgnoredSymbols(): array
    {
        $ignoredSymbols = [
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

        /** @var string $constantName */
        foreach (get_defined_constants() as $constantName => $constantValue) {
            $ignoredSymbols[$constantName] = true;
        }

        foreach (get_defined_functions() as $functionNames) {
            foreach ($functionNames as $functionName) {
                $ignoredSymbols[$functionName] = true;
            }
        }

        $classLikes = [
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits(),
        ];

        foreach ($classLikes as $classLikeNames) {
            foreach ($classLikeNames as $classLikeName) {
                if ((new ReflectionClass($classLikeName))->getExtension() !== null) {
                    $ignoredSymbols[$classLikeName] = true;
                }
            }
        }

        return array_change_key_case($ignoredSymbols, CASE_LOWER); // get_defined_functions returns lowercase functions
    }

}
