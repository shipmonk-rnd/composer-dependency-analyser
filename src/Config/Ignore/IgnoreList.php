<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use LogicException;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use function array_fill_keys;
use function array_flip;
use function get_defined_constants;
use function preg_last_error;
use function preg_match;
use function strpos;

class IgnoreList
{

    /**
     * @var array<ErrorType::*, bool>
     */
    private $ignoredErrors;

    /**
     * @var array<string, array<ErrorType::*, bool>>
     */
    private $ignoredErrorsOnPath = [];

    /**
     * @var array<string, array<ErrorType::*, bool>>
     */
    private $ignoredErrorsOnPackage = [];

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownClasses;

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownClassesRegexes;

    /**
     * @param list<ErrorType::*> $ignoredErrors
     * @param array<string, list<ErrorType::*>> $ignoredErrorsOnPath
     * @param array<string, list<ErrorType::*>> $ignoredErrorsOnPackage
     * @param list<string> $ignoredUnknownClasses
     * @param list<string> $ignoredUnknownClassesRegexes
     */
    public function __construct(
        array $ignoredErrors,
        array $ignoredErrorsOnPath,
        array $ignoredErrorsOnPackage,
        array $ignoredUnknownClasses,
        array $ignoredUnknownClassesRegexes
    )
    {
        $this->ignoredErrors = array_fill_keys($ignoredErrors, false);

        foreach ($ignoredErrorsOnPath as $path => $errorTypes) {
            $this->ignoredErrorsOnPath[$path] = array_fill_keys($errorTypes, false);
        }

        foreach ($ignoredErrorsOnPackage as $packageName => $errorTypes) {
            $this->ignoredErrorsOnPackage[$packageName] = array_fill_keys($errorTypes, false);
        }

        $this->ignoredUnknownClasses = array_fill_keys($ignoredUnknownClasses, false);
        $this->ignoredUnknownClassesRegexes = array_fill_keys($ignoredUnknownClassesRegexes, false);
    }

    /**
     * @return list<UnusedErrorIgnore|UnusedClassIgnore>
     */
    public function getUnusedIgnores(): array
    {
        $unused = [];

        foreach ($this->ignoredErrors as $errorType => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedErrorIgnore($errorType, null, null);
            }
        }

        foreach ($this->ignoredErrorsOnPath as $path => $errorTypes) {
            foreach ($errorTypes as $errorType => $ignored) {
                if (!$ignored) {
                    $unused[] = new UnusedErrorIgnore($errorType, $path, null);
                }
            }
        }

        foreach ($this->ignoredErrorsOnPackage as $packageName => $errorTypes) {
            foreach ($errorTypes as $errorType => $ignored) {
                if (!$ignored) {
                    $unused[] = new UnusedErrorIgnore($errorType, null, $packageName);
                }
            }
        }

        foreach ($this->ignoredUnknownClasses as $class => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedClassIgnore($class, false);
            }
        }

        foreach ($this->ignoredUnknownClassesRegexes as $regex => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedClassIgnore($regex, true);
            }
        }

        return $unused;
    }

    public function shouldIgnoreUnknownClass(string $class): bool
    {
        if (isset($this->ignoredUnknownClasses[$class])) {
            $this->ignoredUnknownClasses[$class] = true;
            return true;
        }

        foreach ($this->ignoredUnknownClassesRegexes as $regex => $ignoreUsed) {
            $matches = preg_match($regex, $class);

            if ($matches === false) {
                /** @var array<string, int> $pcreConstants */
                $pcreConstants = get_defined_constants(true)['pcre'] ?? [];
                $error = array_flip($pcreConstants)[preg_last_error()] ?? 'unknown error';
                throw new LogicException("Invalid regex: '$regex', error: $error");
            }

            if ($matches === 1) {
                $this->ignoredUnknownClassesRegexes[$regex] = true;
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
        if (isset($this->ignoredErrors[$errorType])) {
            $this->ignoredErrors[$errorType] = true;
            return true;
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPath(string $errorType, string $filePath): bool
    {
        foreach ($this->ignoredErrorsOnPath as $path => $errorTypes) {
            if ($this->isFilepathWithinPath($filePath, $path) && isset($errorTypes[$errorType])) {
                $this->ignoredErrorsOnPath[$path][$errorType] = true;
                return true;
            }
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPackage(string $errorType, string $packageName): bool
    {
        if (isset($this->ignoredErrorsOnPackage[$packageName][$errorType])) {
            $this->ignoredErrorsOnPackage[$packageName][$errorType] = true;
            return true;
        }

        return false;
    }

    private function isFilepathWithinPath(string $filePath, string $path): bool
    {
        return strpos($filePath, $path) === 0;
    }

}
