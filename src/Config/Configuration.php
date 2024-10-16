<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\IgnoreList;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidConfigException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use ShipMonk\ComposerDependencyAnalyser\Path;
use function array_merge;
use function in_array;
use function preg_match;
use function strpos;

class Configuration
{

    /**
     * @var bool
     */
    private $extensionsAnalysis = true;

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
     * @var list<string>
     */
    private $pathRegexesToExclude = [];

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
     * @var list<string>
     */
    private $ignoredUnknownFunctions = [];

    /**
     * @var list<string>
     */
    private $ignoredUnknownFunctionsRegexes = [];

    /**
     * Disable analysis of ext-* dependencies
     *
     * @return $this
     */
    public function disableExtensionsAnalysis(): self
    {
        $this->extensionsAnalysis = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableComposerAutoloadPathScan(): self
    {
        $this->scanComposerAutoloadPaths = false;
        return $this;
    }

    /**
     * @return $this
     */
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
     * @throws InvalidPathException
     */
    public function addPathToScan(string $path, bool $isDev): self
    {
        $this->pathsToScan[] = new PathToScan(Path::realpath($path), $isDev);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @return $this
     * @throws InvalidPathException
     */
    public function addPathsToScan(array $paths, bool $isDev): self
    {
        foreach ($paths as $path) {
            $this->addPathToScan($path, $isDev);
        }

        return $this;
    }

    /**
     * @param list<string> $paths
     * @return $this
     * @throws InvalidPathException
     */
    public function addPathsToExclude(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addPathToExclude($path);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws InvalidPathException
     */
    public function addPathToExclude(string $path): self
    {
        $this->pathsToExclude[] = Path::realpath($path);
        return $this;
    }

    /**
     * @param list<string> $regexes
     * @return $this
     * @throws InvalidConfigException
     */
    public function addPathRegexesToExclude(array $regexes): self
    {
        foreach ($regexes as $regex) {
            $this->addPathRegexToExclude($regex);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws InvalidConfigException
     */
    public function addPathRegexToExclude(string $regex): self
    {
        if (@preg_match($regex, '') === false) {
            throw new InvalidConfigException("Invalid regex '$regex'");
        }

        $this->pathRegexesToExclude[] = $regex;
        return $this;
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     * @throws InvalidPathException
     * @throws InvalidConfigException
     */
    public function ignoreErrorsOnPath(string $path, array $errorTypes): self
    {
        $this->checkAllowedErrorTypeForPathIgnore($errorTypes);

        $realpath = Path::realpath($path);

        $previousErrorTypes = $this->ignoredErrorsOnPath[$realpath] ?? [];
        $this->ignoredErrorsOnPath[$realpath] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     * @throws InvalidPathException
     * @throws InvalidConfigException
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
     * @throws InvalidConfigException
     */
    public function ignoreErrorsOnPackage(string $packageName, array $errorTypes): self
    {
        $this->checkPackageName($packageName);
        $this->checkAllowedErrorTypeForPackageIgnore($errorTypes);

        $previousErrorTypes = $this->ignoredErrorsOnPackage[$packageName] ?? [];
        $this->ignoredErrorsOnPackage[$packageName] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $packageNames
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     * @throws InvalidConfigException
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
     * @throws InvalidPathException
     * @throws InvalidConfigException
     */
    public function ignoreErrorsOnPackageAndPath(string $packageName, string $path, array $errorTypes): self
    {
        $this->checkPackageName($packageName);
        $this->checkAllowedErrorTypeForPathIgnore($errorTypes);
        $this->checkAllowedErrorTypeForPackageIgnore($errorTypes);

        $realpath = Path::realpath($path);

        $previousErrorTypes = $this->ignoredErrorsOnPackageAndPath[$packageName][$realpath] ?? [];
        $this->ignoredErrorsOnPackageAndPath[$packageName][$realpath] = array_merge($previousErrorTypes, $errorTypes);
        return $this;
    }

    /**
     * @param list<string> $paths
     * @param list<ErrorType::*> $errorTypes
     * @return $this
     * @throws InvalidPathException
     * @throws InvalidConfigException
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
     * @throws InvalidPathException
     * @throws InvalidConfigException
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
     * @param list<string> $functionNames
     * @return $this
     */
    public function ignoreUnknownFunctions(array $functionNames): self
    {
        $this->ignoredUnknownFunctions = array_merge($this->ignoredUnknownFunctions, $functionNames);

        return $this;
    }

    /**
     * @return $this
     * @throws InvalidConfigException
     */
    public function ignoreUnknownClassesRegex(string $classNameRegex): self
    {
        if (@preg_match($classNameRegex, '') === false) {
            throw new InvalidConfigException("Invalid regex '$classNameRegex'");
        }

        $this->ignoredUnknownClassesRegexes[] = $classNameRegex;
        return $this;
    }

    /**
     * @return $this
     * @throws InvalidConfigException
     */
    public function ignoreUnknownFunctionsRegex(string $functionNameRegex): self
    {
        if (@preg_match($functionNameRegex, '') === false) {
            throw new InvalidConfigException("Invalid regex '$functionNameRegex'");
        }

        $this->ignoredUnknownFunctionsRegexes[] = $functionNameRegex;
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
            $this->ignoredUnknownClassesRegexes,
            $this->ignoredUnknownFunctions,
            $this->ignoredUnknownFunctionsRegexes
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

    public function shouldAnalyseExtensions(): bool
    {
        return $this->extensionsAnalysis;
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

        foreach ($this->pathRegexesToExclude as $pathRegexToExclude) {
            if ((bool) preg_match($pathRegexToExclude, $filePath)) {
                return true;
            }
        }

        return false;
    }

    private function isFilepathWithinPath(string $filePath, string $path): bool
    {
        return strpos($filePath, $path) === 0;
    }

    /**
     * @throws InvalidConfigException
     */
    private function checkPackageName(string $packageName): void
    {
        $regex = '~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~'; // https://getcomposer.org/doc/04-schema.md

        if (preg_match($regex, $packageName) !== 1) {
            throw new InvalidConfigException("Invalid package name '$packageName'");
        }
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @throws InvalidConfigException
     */
    private function checkAllowedErrorTypeForPathIgnore(array $errorTypes): void
    {
        if (in_array(ErrorType::UNUSED_DEPENDENCY, $errorTypes, true)) {
            throw new InvalidConfigException('UNUSED_DEPENDENCY errors cannot be ignored on a path');
        }

        if (in_array(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, $errorTypes, true)) {
            throw new InvalidConfigException('PROD_DEPENDENCY_ONLY_IN_DEV errors cannot be ignored on a path');
        }
    }

    /**
     * @param list<ErrorType::*> $errorTypes
     * @throws InvalidConfigException
     */
    private function checkAllowedErrorTypeForPackageIgnore(array $errorTypes): void
    {
        if (in_array(ErrorType::UNKNOWN_CLASS, $errorTypes, true)) {
            throw new InvalidConfigException('UNKNOWN_CLASS errors cannot be ignored on a package');
        }

        if (in_array(ErrorType::UNKNOWN_FUNCTION, $errorTypes, true)) {
            throw new InvalidConfigException('UNKNOWN_FUNCTION errors cannot be ignored on a package');
        }
    }

}
