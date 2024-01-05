<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use DirectoryIterator;
use Generator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ShipMonk\Composer\Config\Configuration;
use ShipMonk\Composer\Crate\ClassUsage;
use ShipMonk\Composer\Enum\ErrorType;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\SymbolError;
use ShipMonk\Composer\Error\UnusedDependencyError;
use UnexpectedValueException;
use function array_diff;
use function array_filter;
use function array_keys;
use function array_values;
use function class_exists;
use function defined;
use function explode;
use function file_get_contents;
use function function_exists;
use function get_class;
use function interface_exists;
use function is_file;
use function realpath;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use function usort;
use const DIRECTORY_SEPARATOR;

class ComposerDependencyAnalyser
{

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var string
     */
    private $vendorDir;

    /**
     * Contents of vendor/composer/autoload_classmap.php after composer dump-autoload -o was called
     *
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
     */
    public function __construct(
        Configuration $config,
        string $vendorDir,
        array $optimizedClassmap,
        array $composerJsonDependencies
    )
    {
        foreach ($optimizedClassmap as $className => $filePath) {
            $this->optimizedClassmap[$className] = $this->realPath($filePath);
        }

        $this->config = $config;
        $this->vendorDir = $this->realPath($vendorDir);
        $this->composerJsonDependencies = $composerJsonDependencies;
    }

    /**
     * @return list<SymbolError>
     */
    public function run(): array
    {
        $errors = [];
        $usedPackages = [];

        foreach ($this->config->getPathsToScan() as $scanPath) {
            foreach ($this->listPhpFilesIn($scanPath->getPath()) as $filePath) {
                if ($this->config->isExcludedFilepath($filePath)) {
                    continue;
                }

                foreach ($this->getUsedSymbolsInFile($filePath) as $usedSymbol => $lineNumber) {
                    if ($this->isInternalClass($usedSymbol)) {
                        continue;
                    }

                    if ($this->isComposerInternalClass($usedSymbol)) {
                        continue;
                    }

                    if (!isset($this->optimizedClassmap[$usedSymbol])) {
                        if (
                            !$this->isConstOrFunction($usedSymbol)
                            && !$this->config->shouldIgnoreUnknownClass($usedSymbol)
                            && !$this->config->shouldIgnoreError(ErrorType::UNKNOWN_CLASS, $filePath, null)
                        ) {
                            $errors[$usedSymbol] = new ClassmapEntryMissingError(new ClassUsage($usedSymbol, $filePath, $lineNumber));
                        }

                        continue;
                    }

                    $classmapPath = $this->optimizedClassmap[$usedSymbol];

                    if (!$this->isVendorPath($classmapPath)) {
                        continue; // local class
                    }

                    $packageName = $this->getPackageNameFromVendorPath($classmapPath);

                    if (
                        $this->isShadowDependency($packageName)
                        && !$this->config->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, $filePath, $packageName)
                    ) {
                        $errors[$packageName] = new ShadowDependencyError($packageName, new ClassUsage($usedSymbol, $filePath, $lineNumber));
                    }

                    if (
                        !$scanPath->isDev()
                        && $this->isDevDependency($packageName)
                        && !$this->config->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, $filePath, $packageName)
                    ) {
                        $errors[$packageName] = new DevDependencyInProductionCodeError($packageName, new ClassUsage($usedSymbol, $filePath, $lineNumber));
                    }

                    $usedPackages[$packageName] = true;
                }
            }
        }

        $unusedDependencies = array_diff(
            array_keys(array_filter($this->composerJsonDependencies, static function (bool $devDependency) {
                return !$devDependency; // dev deps are typically used only in CI
            })),
            array_keys($usedPackages)
        );

        foreach ($unusedDependencies as $unusedDependency) {
            if (!$this->config->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, null, $unusedDependency)) {
                $errors[] = new UnusedDependencyError($unusedDependency);
            }
        }

        usort($errors, static function (SymbolError $a, SymbolError $b): int {
            $aPackageName = $a->getPackageName() ?? '';
            $bPackageName = $b->getPackageName() ?? '';
            $aClassName = $a->getExampleUsage() !== null ? $a->getExampleUsage()->getClassname() : '';
            $bClassName = $b->getExampleUsage() !== null ? $b->getExampleUsage()->getClassname() : '';

            return [get_class($a), $aPackageName, $aClassName] <=> [get_class($b), $bPackageName, $bClassName];
        });

        return array_values($errors);
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
     * @return array<string, int>
     */
    private function getUsedSymbolsInFile(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new LogicException("Unable to get contents of $filePath");
        }

        return (new UsedSymbolExtractor($code))->parseUsedClasses();
    }

    /**
     * @return Generator<string>
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
            throw new LogicException("Unable to list files in $path", 0, $e);
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

    private function realPath(string $filePath): string
    {
        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new LogicException("Unable to realpath '$filePath'");
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
        return $usedSymbol === 'Composer\\InstalledVersions';
    }

}
