<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Composer\Autoload\ClassLoader;
use DirectoryIterator;
use Generator;
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
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function explode;
use function file_get_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
use function is_file;
use function ksort;
use function realpath;
use function sort;
use function str_replace;
use function strlen;
use function strpos;
use function strtr;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

class Analyser
{

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @var ClassLoader
     */
    private $classLoader;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var string
     */
    private $vendorDir;

    /**
     * className => realPath
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
     * @param array<string, bool> $composerJsonDependencies package name => is dev dependency
     * @throws InvalidPathException
     */
    public function __construct(
        Stopwatch $stopwatch,
        ClassLoader $classLoader,
        Configuration $config,
        string $vendorDir,
        array $composerJsonDependencies
    )
    {
        $this->stopwatch = $stopwatch;
        $this->classLoader = $classLoader;
        $this->config = $config;
        $this->vendorDir = $this->realPath($vendorDir);
        $this->composerJsonDependencies = $composerJsonDependencies;
        $this->ignoredSymbols = $this->getIgnoredSymbols();
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

        $ignoreList = $this->config->getIgnoreList();

        foreach ($this->getUniqueFilePathsToScan() as $filePath => $isDevFilePath) {
            $scannedFilesCount++;

            foreach ($this->getUsedSymbolsInFile($filePath) as $usedSymbol => $lineNumbers) {
                if (isset($this->ignoredSymbols[$usedSymbol])) {
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

        ksort($classmapErrors);
        ksort($shadowErrors);
        ksort($devInProdErrors);
        sort($prodOnlyInDevErrors);
        sort($unusedErrors);

        return new AnalysisResult(
            $scannedFilesCount,
            $this->stopwatch->stop(),
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
        $filePathInVendor = trim(str_replace($this->vendorDir, '', $realPath), DIRECTORY_SEPARATOR);
        [$vendor, $package] = explode(DIRECTORY_SEPARATOR, $filePathInVendor, 3);
        return "$vendor/$package";
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
            if (!$entry->isFile() || !$this->isExtensionToCheck($entry->getFilename())) {
                continue;
            }

            yield $entry->getPathname();
        }
    }

    private function isExtensionToCheck(string $filePath): bool
    {
        foreach ($this->config->getFileExtensions() as $extension) {
            if (substr($filePath, -(strlen($extension) + 1)) === ".$extension") {
                return true;
            }
        }

        return false;
    }

    private function isVendorPath(string $realPath): bool
    {
        return substr($realPath, 0, strlen($this->vendorDir)) === $this->vendorDir;
    }

    private function getSymbolPath(string $symbol): ?string
    {
        if (!array_key_exists($symbol, $this->classmap)) {
            $this->classmap[$symbol] = $this->detectFileByClassLoader($symbol) ?? $this->detectFileByReflection($symbol);
        }

        return $this->classmap[$symbol];
    }

    /**
     * @throws InvalidPathException
     */
    private function realPath(string $filePath): string
    {
        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new InvalidPathException("'$filePath' is not a file nor directory");
        }

        return $realPath;
    }

    /**
     * This should minimize the amount autoloaded classes
     */
    private function detectFileByClassLoader(string $usedSymbol): ?string
    {
        $filePath = $this->classLoader->findFile($usedSymbol);

        if ($filePath === false) {
            return null;
        }

        try {
            return $this->realPath($filePath);
        } catch (InvalidPathException $e) {
            return null;
        }
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

        return strtr($this->trimPharPrefix($filePath), '/', DIRECTORY_SEPARATOR);
    }

    private function trimPharPrefix(string $filePath): string
    {
        $pharPrefix = 'phar://';

        if (strpos($filePath, $pharPrefix) === 0) {
            return substr($filePath, strlen($pharPrefix)); // @phpstan-ignore-line substr cannot return false here
        }

        return $filePath;
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

        return $ignoredSymbols;
    }

}
