<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use DirectoryIterator;
use Generator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\SymbolError;
use UnexpectedValueException;
use function class_exists;
use function defined;
use function explode;
use function file_get_contents;
use function function_exists;
use function interface_exists;
use function is_file;
use function ksort;
use function realpath;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

class ComposerDependencyAnalyser
{

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
     * @var list<string>
     */
    private $extensionsToCheck;

    /**
     * @param array<string, string> $optimizedClassmap className => filePath
     * @param array<string, bool> $composerJsonDependencies package name => is dev dependency
     * @param list<string> $extensionsToCheck
     */
    public function __construct(
        string $vendorDir,
        array $optimizedClassmap,
        array $composerJsonDependencies,
        array $extensionsToCheck = ['php']
    )
    {
        foreach ($optimizedClassmap as $className => $filePath) {
            $this->optimizedClassmap[$className] = $this->realPath($filePath);
        }

        $this->vendorDir = $this->realPath($vendorDir);
        $this->composerJsonDependencies = $composerJsonDependencies;
        $this->extensionsToCheck = $extensionsToCheck;
    }

    /**
     * @param array<string, bool> $scanPaths path => is dev path
     * @return array<string, SymbolError>
     */
    public function scan(array $scanPaths): array
    {
        $errors = [];

        foreach ($scanPaths as $scanPath => $isDevPath) {
            foreach ($this->listPhpFilesIn($scanPath) as $filePath) {
                foreach ($this->getUsedSymbolsInFile($filePath) as $usedSymbol) {
                    if ($this->isInternalClass($usedSymbol)) {
                        continue;
                    }

                    if (!isset($this->optimizedClassmap[$usedSymbol])) {
                        if (!$this->isConstOrFunction($usedSymbol)) {
                            $errors[$usedSymbol] = new ClassmapEntryMissingError($usedSymbol, $filePath);
                        }

                        continue;
                    }

                    $classmapPath = $this->optimizedClassmap[$usedSymbol];

                    if (!$this->isVendorPath($classmapPath)) {
                        continue; // local class
                    }

                    $packageName = $this->getPackageNameFromVendorPath($classmapPath);

                    if ($this->isShadowDependency($packageName)) {
                        $errors[$usedSymbol] = new ShadowDependencyError($usedSymbol, $packageName, $filePath);
                    }

                    if (!$isDevPath && $this->isDevDependency($packageName)) {
                        $errors[$usedClass] = new DevDependencyInProductionCodeError($usedClass, $packageName, $filePath);
                    }
                }
            }
        }

        ksort($errors);

        return $errors;
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
     * @return list<string>
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
        foreach ($this->extensionsToCheck as $extension) {
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

}
