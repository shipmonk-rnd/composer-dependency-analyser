<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use DirectoryIterator;
use Generator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use UnexpectedValueException;
use function array_merge;
use function class_exists;
use function explode;
use function file_get_contents;
use function interface_exists;
use function is_file;
use function realpath;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

class ShadowDependencyDetector
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
     * @param list<string> $scanPaths
     * @return list<string>
     */
    public function scan(array $scanPaths): array
    {
        $errors = [];

        $usedClasses = [];

        foreach ($scanPaths as $scanPath) {
            foreach ($this->listPhpFilesIn($scanPath) as $filePath) {
                $usedClasses = array_merge($usedClasses, $this->getUsesInFile($filePath));
            }
        }

        foreach ($usedClasses as $usedClass) {
            if ($this->isInternalClass($usedClass)) {
                continue;
            }

            if (!isset($this->optimizedClassmap[$usedClass])) {
                $errors[] = $usedClass . ' not found in classmap (precondition violated?)';
                continue;
            }

            $filePath = $this->optimizedClassmap[$usedClass];

            if ($this->isLocalClass($filePath)) {
                continue;
            }

            if ($this->isShadowDependency($filePath)) {
                $errors[] = "$usedClass used as shadow dependency!";
            }
        }

        return $errors;
    }

    private function isShadowDependency(string $realPath): bool
    {
        $packageName = $this->getPackageNameFromVendorPath($realPath);

        return !isset($this->composerJsonDependencies[$packageName]);
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
    private function getUsesInFile(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new LogicException("Unable to get contents of $filePath");
        }

        $extractor = new UsedSymbolExtractor($code);
        return $extractor->parseUsedSymbols();
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

    private function isLocalClass(string $realPath): bool
    {
        return substr($realPath, 0, strlen($this->vendorDir)) !== $this->vendorDir;
    }

    private function realPath(string $filePath): string
    {
        $realPath = realpath($filePath);

        if ($realPath === false) {
            throw new LogicException("Unable to realpath '$filePath'");
        }

        return $realPath;
    }

}
