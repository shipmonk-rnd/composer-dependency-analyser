<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use DirectoryIterator;
use Generator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use UnexpectedValueException;
use function array_diff;
use function array_filter;
use function array_keys;
use function class_exists;
use function defined;
use function explode;
use function file_get_contents;
use function function_exists;
use function in_array;
use function interface_exists;
use function is_dir;
use function is_file;
use function is_readable;
use function ksort;
use function realpath;
use function sort;
use function str_replace;
use function strlen;
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
     * @var array<string, string>
     */
    private $optimizedClassmap;

    /**
     * package name => is dev dependency
     *
     * @var array<string, bool>
     */
    private $composerJsonDependencies;

    /**
     * @param array<string, string> $optimizedClassmap className => filePath
     * @param array<string, bool> $composerJsonDependencies package name => is dev dependency
     * @throws InvalidPathException
     */
    public function __construct(
        Stopwatch $stopwatch,
        Configuration $config,
        string $vendorDir,
        array $optimizedClassmap,
        array $composerJsonDependencies
    )
    {
        foreach ($optimizedClassmap as $className => $filePath) {
            $this->optimizedClassmap[$className] = $this->realPath($filePath);
        }

        $this->stopwatch = $stopwatch;
        $this->config = $config;
        $this->vendorDir = $this->realPath($vendorDir);
        $this->composerJsonDependencies = $composerJsonDependencies;
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

        foreach ($this->config->getPathsToScan() as $scanPath) {
            foreach ($this->listPhpFilesIn($scanPath->getPath()) as $filePath) {
                if ($this->config->isExcludedFilepath($filePath)) {
                    continue;
                }

                $scannedFilesCount++;

                foreach ($this->getUsedSymbolsInFile($filePath) as $usedSymbol => $lineNumbers) {
                    if ($this->isInternalClass($usedSymbol)) {
                        continue;
                    }

                    if ($this->isComposerInternalClass($usedSymbol)) {
                        continue;
                    }

                    if (!$this->isInClassmap($usedSymbol)) {
                        if (
                            !$this->isConstOrFunction($usedSymbol)
                            && !$ignoreList->shouldIgnoreUnknownClass($usedSymbol)
                            && !$ignoreList->shouldIgnoreError(ErrorType::UNKNOWN_CLASS, $filePath, null)
                        ) {
                            foreach ($lineNumbers as $lineNumber) {
                                $classmapErrors[$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                            }
                        }

                        continue;
                    }

                    $classmapPath = $this->getPathFromClassmap($usedSymbol);

                    if (!$this->isVendorPath($classmapPath)) {
                        continue; // local class
                    }

                    $packageName = $this->getPackageNameFromVendorPath($classmapPath);

                    if (
                        $this->isShadowDependency($packageName)
                        && !$ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, $filePath, $packageName)
                    ) {
                        foreach ($lineNumbers as $lineNumber) {
                            $shadowErrors[$packageName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                        }
                    }

                    if (
                        !$scanPath->isDev()
                        && $this->isDevDependency($packageName)
                        && !$ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, $filePath, $packageName)
                    ) {
                        foreach ($lineNumbers as $lineNumber) {
                            $devInProdErrors[$packageName][$usedSymbol][] = new SymbolUsage($filePath, $lineNumber);
                        }
                    }

                    if (
                        !$scanPath->isDev()
                        && !$this->isDevDependency($packageName)
                    ) {
                        $prodPackagesUsedInProdPath[$packageName] = true;
                    }

                    $usedPackages[$packageName] = true;
                }
            }
        }

        $forceUsedPackages = [];

        foreach ($this->config->getForceUsedSymbols() as $forceUsedSymbol) {
            if (!$this->isInClassmap($forceUsedSymbol)) {
                continue;
            }

            $classmapPath = $this->getPathFromClassmap($forceUsedSymbol);

            if (!$this->isVendorPath($classmapPath)) {
                continue;
            }

            $forceUsedPackage = $this->getPackageNameFromVendorPath($classmapPath);
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
        if (!is_readable($filePath)) {
            throw new InvalidPathException("File '$filePath' is not readable");
        }

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
        if (is_file($path) && $this->isExtensionToCheck($path)) {
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

    private function isInternalClass(string $className): bool
    {
        return (class_exists($className, false) || interface_exists($className, false))
            && (new ReflectionClass($className))->getExtension() !== null;
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

    private function isInClassmap(string $usedSymbol): bool
    {
        return isset($this->optimizedClassmap[$usedSymbol]);
    }

    private function getPathFromClassmap(string $usedSymbol): string
    {
        if (!$this->isInClassmap($usedSymbol)) {
            throw new LogicException("Class $usedSymbol not found in classmap");
        }

        return $this->optimizedClassmap[$usedSymbol];
    }

    /**
     * @throws InvalidPathException
     */
    private function realPath(string $filePath): string
    {
        if (!is_file($filePath) && !is_dir($filePath)) {
            throw new InvalidPathException("'$filePath' is not a file nor directory");
        }

        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new InvalidPathException("Unable to realpath '$filePath'");
        }

        return $realPath;
    }

    /**
     * Since UsedSymbolExtractor cannot reliably tell if FQN usages are classes or other symbols,
     * we verify those edgecases only when such classname is not found in classmap.
     */
    private function isConstOrFunction(string $usedClass): bool
    {
        return defined($usedClass) || function_exists($usedClass);
    }

    /**
     * Those are always available: https://getcomposer.org/doc/07-runtime.md#installed-versions
     */
    private function isComposerInternalClass(string $usedSymbol): bool
    {
        return in_array($usedSymbol, [
            'Composer\\InstalledVersions',
            'Composer\\Autoload\\ClassLoader'
        ], true);
    }

}
