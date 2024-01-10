<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

use LogicException;
use function array_flip;
use function array_keys;
use function array_merge;
use function get_defined_constants;
use function in_array;
use function preg_last_error;
use function preg_match;
use function realpath;
use function strpos;

class Configuration
{

    /**
     * @var bool
     */
    private $scanComposerAutoloadPaths = true;

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
            throw new LogicException('Unused dependency errors cannot be ignored on a path');
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
     * @param list<string> $classNames
     * @return $this
     */
    public function ignoreUnknownClasses(array $classNames): self
    {
        $this->ignoredUnknownClasses = $classNames;

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

    // getters below

    /**
     * @return list<string>
     */
    public function getFileExtensions(): array
    {
        return $this->fileExtensions;
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

    public function shouldIgnoreUnknownClass(string $class): bool
    {
        if (in_array($class, $this->ignoredUnknownClasses, true)) {
            return true;
        }

        foreach ($this->ignoredUnknownClassesRegexes as $regex) {
            $matches = preg_match($regex, $class);

            if ($matches === false) {
                /** @var array<string, int> $pcreConstants */
                $pcreConstants = get_defined_constants(true)['pcre'] ?? [];
                $error = array_flip($pcreConstants)[preg_last_error()] ?? 'unknown error';
                throw new LogicException("Invalid regex: '$regex', error: $error");
            }

            if ($matches === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    public function shouldIgnoreError(string $errorType, ?string $filePath, ?string $packageName): bool
    {
        if ($this->shouldIgnoreErrorGlobally($errorType)) {
            return true;
        }

        if ($filePath !== null && $this->shouldIgnoreErrorOnPath($errorType, $filePath)) {
            return true;
        }

        if ($packageName !== null && $this->shouldIgnoreErrorOnPackage($errorType, $packageName)) {
            return true;
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorGlobally(string $errorType): bool
    {
        return in_array($errorType, $this->ignoredErrors, true);
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPath(string $errorType, string $filePath): bool
    {
        foreach ($this->ignoredErrorsOnPath as $path => $errorTypes) {
            if ($this->isFilepathWithinPath($filePath, $path)) {
                return in_array($errorType, $errorTypes, true);
            }
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPackage(string $errorType, string $packageName): bool
    {
        return in_array($errorType, $this->ignoredErrorsOnPackage[$packageName] ?? [], true);
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
