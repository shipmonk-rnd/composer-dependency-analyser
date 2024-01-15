<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

use LogicException;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\IgnoreList;
use function array_keys;
use function array_merge;
use function in_array;
use function realpath;
use function strpos;

class Configuration
{

    /**
     * @var bool
     */
    private $scanComposerAutoloadPaths = true;

    /**
     * @var bool
     */
    private $reportUnusedDevDependencies = false;

    /**
     * @var bool
     */
    private $reportUnmatchedIgnores = true;

    /**
     * @var list<string>
     */
    private $forceUsedSymbols = [];

    /**
     * @var list<ErrorType::*>
     */
    private $ignoredErrors = [];

    /**
     * @var list<string>
     */
    private $fileExtensions = ['php'];

    /**
     * @var list<PathToScan>
     */
    private $pathsToScan = [];

    /**
     * @var list<string>
     */
    private $pathsToExclude = [];

    /**
     * @var array<string, list<ErrorType::*>>
     */
    private $ignoredErrorsOnPath = [];

    /**
     * @var array<string, list<ErrorType::*>>
     */
    private $ignoredErrorsOnPackage = [];

    /**
     * @var array<string, array<string, list<ErrorType::*>>>
     */
    private $ignoredErrorsOnPackageAndPath = [];

    /**
     * @var list<string>
     */
    private $ignoredUnknownClasses = [];

    /**
     * @var list<string>
     */
    private $ignoredUnknownClassesRegexes = [];

    /**
     * @return $this
     */
    public function disableComposerAutoloadPathScan(): self
    {
        $this->scanComposerAutoloadPaths = false;
        return $this;
    }

    public function disableReportingUnmatchedIgnores(): self
    {
        $this->reportUnmatchedIgnores = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function enableAnalysisOfUnusedDevDependencies(): self
    {
        $this->reportUnusedDevDependencies = true;
        return $this;
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrors(array $errorTypes): self
    {
        $this->ignoredErrors = array_merge($this->ignoredErrors, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $extensions
     * @return $this
     */
    public function setFileExtensions(array $extensions): self
    {
        $this->fileExtensions = $extensions;
        return $this;
    }

    /**
     * @return $this
     */
    public function addForceUsedSymbol(string $symbol): self
    {
        $this->forceUsedSymbols[] = $symbol;
        return $this;
    }

    /**
     * @param list<string> $symbols
     * @return $this
     */
    public function addForceUsedSymbols(array $symbols): self
    {
        foreach ($symbols as $symbol) {
            $this->addForceUsedSymbol($symbol);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addPathToScan(string $path, bool $isDev): self
    {
        $this->pathsToScan[] = new PathToScan($this->realpath($path), $isDev);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @return $this
     */
    public function addPathsToScan(array $paths, bool $isDev): self
    {
        foreach ($paths as $path) {
            $this->addPathToScan($path, $isDev);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addPathToExclude(string $path): self
    {
        $this->pathsToExclude[] = $this->realpath($path);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @return $this
     */
    public function addPathsToExclude(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addPathToExclude($path);
        }

        return $this;
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPath(string $path, array $errorTypes): self
    {
        if (in_array(ErrorType::UNUSED_DEPENDENCY, $errorTypes, true)) {
            throw new LogicException('UNUSED_DEPENDENCY errors cannot be ignored on a path');
        }

        if (in_array(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, $errorTypes, true)) {
            throw new LogicException('PROD_DEPENDENCY_ONLY_IN_DEV errors cannot be ignored on a path');
        }

        $realpath = $this->realpath($path);

        $previousErrorTypes = $this->ignoredErrorsOnPath[$realpath] ?? [];
        $this->ignoredErrorsOnPath[$realpath] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPaths(array $paths, array $errorTypes): self
    {
        foreach ($paths as $path) {
            $this->ignoreErrorsOnPath($path, $errorTypes);
        }

        return $this;
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPackage(string $packageName, array $errorTypes): self
    {
        if (in_array(ErrorType::UNKNOWN_CLASS, $errorTypes, true)) {
            throw new LogicException('Unknown class errors cannot be ignored on a package');
        }

        $previousErrorTypes = $this->ignoredErrorsOnPackage[$packageName] ?? [];
        $this->ignoredErrorsOnPackage[$packageName] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $packageNames
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPackages(array $packageNames, array $errorTypes): self
    {
        foreach ($packageNames as $packageName) {
            $this->ignoreErrorsOnPackage($packageName, $errorTypes);
        }

        return $this;
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPackageAndPath(string $packageName, string $path, array $errorTypes): self
    {
        if (in_array(ErrorType::UNKNOWN_CLASS, $errorTypes, true)) {
            throw new LogicException('UNKNOWN_CLASS errors cannot be ignored on a package');
        }

        if (in_array(ErrorType::UNUSED_DEPENDENCY, $errorTypes, true)) {
            throw new LogicException('UNUSED_DEPENDENCY errors cannot be ignored on a path');
        }

        if (in_array(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, $errorTypes, true)) {
            throw new LogicException('PROD_DEPENDENCY_ONLY_IN_DEV errors cannot be ignored on a path');
        }

        $previousErrorTypes = $this->ignoredErrorsOnPackageAndPath[$packageName][$path] ?? [];
        $this->ignoredErrorsOnPackageAndPath[$packageName][$path] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPackageAndPaths(string $packageName, array $paths, array $errorTypes): self
    {
        foreach ($paths as $path) {
            $this->ignoreErrorsOnPackageAndPath($packageName, $path, $errorTypes);
        }

        return $this;
    }

    /**
     * @param list<string> $packages
     * @param list<string> $paths
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     */
    public function ignoreErrorsOnPackagesAndPaths(array $packages, array $paths, array $errorTypes): self
    {
        foreach ($packages as $package) {
            $this->ignoreErrorsOnPackageAndPaths($package, $paths, $errorTypes);
        }

        return $this;
    }

    /**
     * @param list<string> $classNames
     * @return $this
     */
    public function ignoreUnknownClasses(array $classNames): self
    {
        $this->ignoredUnknownClasses = array_merge($this->ignoredUnknownClasses, $classNames);

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreUnknownClassesRegex(string $classNameRegex): self
    {
        $this->ignoredUnknownClassesRegexes[] = $classNameRegex;
        return $this;
    }

    public function getIgnoreList(): IgnoreList
    {
        return new IgnoreList(
            $this->ignoredErrors,
            $this->ignoredErrorsOnPath,
            $this->ignoredErrorsOnPackage,
            $this->ignoredErrorsOnPackageAndPath,
            $this->ignoredUnknownClasses,
            $this->ignoredUnknownClassesRegexes
        );
    }

    /**
     * @return list<string>
     */
    public function getFileExtensions(): array
    {
        return $this->fileExtensions;
    }

    /**
     * @return list<string>
     */
    public function getForceUsedSymbols(): array
    {
        return $this->forceUsedSymbols;
    }

    /**
     * @return list<PathToScan>
     */
    public function getPathsToScan(): array
    {
        return $this->pathsToScan;
    }

    /**
     * @return list<string>
     */
    public function getPathsToExclude(): array
    {
        return $this->pathsToExclude;
    }

    /**
     * @return list<string>
     */
    public function getPathsWithIgnore(): array
    {
        return array_keys($this->ignoredErrorsOnPath);
    }

    public function shouldScanComposerAutoloadPaths(): bool
    {
        return $this->scanComposerAutoloadPaths;
    }

    public function shouldReportUnusedDevDependencies(): bool
    {
        return $this->reportUnusedDevDependencies;
    }

    public function shouldReportUnmatchedIgnoredErrors(): bool
    {
        return $this->reportUnmatchedIgnores;
    }

    public function isExcludedFilepath(string $filePath): bool
    {
        foreach ($this->pathsToExclude as $pathToExclude) {
            if ($this->isFilepathWithinPath($filePath, $pathToExclude)) {
                return true;
            }
        }

        return false;
    }

    private function isFilepathWithinPath(string $filePath, string $path): bool
    {
        return strpos($filePath, $path) === 0;
    }

    private function realpath(string $filePath): string
    {
        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new LogicException("Unable to realpath '$filePath'");
        }

        return $realPath;
    }

}
