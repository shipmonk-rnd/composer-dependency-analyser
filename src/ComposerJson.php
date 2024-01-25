<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidConfigException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use function array_fill_keys;
use function array_filter;
use function array_keys;
use function array_merge;
use function count;
use function dirname;
use function file_get_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function realpath;
use function strpos;
use const ARRAY_FILTER_USE_KEY;
use const JSON_ERROR_NONE;

class ComposerJson
{

    /**
     * Package => isDev
     *
     * @readonly
     * @var array<string, bool>
     */
    public $dependencies;

    /**
     * Absolute path => isDev
     *
     * @readonly
     * @var array<string, bool>
     */
    public $autoloadPaths;

    /**
     * @throws InvalidPathException
     * @throws InvalidConfigException
     */
    public function __construct(string $composerJsonPath)
    {
        $basePath = dirname($composerJsonPath);

        $composerJsonData = $this->parseComposerJson($composerJsonPath);

        $requiredPackages = $composerJsonData['require'] ?? [];
        $requiredDevPackages = $composerJsonData['require-dev'] ?? [];

        $this->autoloadPaths = array_merge(
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload']['psr-0'] ?? [], false),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload']['psr-4'] ?? [], false),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload']['files'] ?? [], false),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload']['classmap'] ?? [], false),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload-dev']['psr-0'] ?? [], true),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload-dev']['psr-4'] ?? [], true),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload-dev']['files'] ?? [], true),
            $this->extractAutoloadPaths($basePath, $composerJsonData['autoload-dev']['classmap'] ?? [], true)
        );

        $filterPackages = static function (string $package): bool {
            return strpos($package, '/') !== false;
        };

        $this->dependencies = array_merge(
            array_fill_keys(array_keys(array_filter($requiredPackages, $filterPackages, ARRAY_FILTER_USE_KEY)), false),
            array_fill_keys(array_keys(array_filter($requiredDevPackages, $filterPackages, ARRAY_FILTER_USE_KEY)), true)
        );

        if (count($this->dependencies) === 0) {
            throw new InvalidConfigException("No packages found in $composerJsonPath file.");
        }
    }

    /**
     * @param array<string|array<string>> $autoload
     * @return array<string, bool>
     * @throws InvalidPathException
     */
    private function extractAutoloadPaths(string $basePath, array $autoload, bool $isDev): array
    {
        $result = [];

        foreach ($autoload as $paths) {
            if (!is_array($paths)) {
                $paths = [$paths];
            }

            foreach ($paths as $path) {
                $absolutePath = $basePath . '/' . $path;

                if (strpos($path, '*') !== false) { // https://getcomposer.org/doc/04-schema.md#classmap
                    $globPaths = glob($absolutePath);

                    if ($globPaths === false) {
                        throw new InvalidPathException("Failure while globbing $absolutePath path.");
                    }

                    foreach ($globPaths as $globPath) {
                        $result[$this->realpath($globPath)] = $isDev;
                    }

                    continue;
                }

                $result[$this->realpath($absolutePath)] = $isDev;
            }
        }

        return $result;
    }

    /**
     * @throws InvalidPathException
     */
    private function realpath(string $path): string
    {
        if (!is_file($path) && !is_dir($path)) {
            throw new InvalidPathException("Path from composer.json '$path' is not a file not a directory.");
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new InvalidPathException("Path from composer.json '$path' is invalid: unable to realpath");
        }

        return $realPath;
    }

    /**
     * @return array{
     *     require?: array<string, string>,
     *     require-dev?: array<string, string>,
     *     autoload?: array{
     *          psr-0?: array<string, string|string[]>,
     *          psr-4?: array<string, string|string[]>,
     *          files?: string[],
     *          classmap?: string[]
     *     },
     *     autoload-dev?: array{
     *          psr-0?: array<string, string|string[]>,
     *          psr-4?: array<string, string|string[]>,
     *          files?: string[],
     *          classmap?: string[]
     *     }
     * }
     * @throws InvalidPathException
     */
    private function parseComposerJson(string $composerJsonPath): array
    {
        if (!is_file($composerJsonPath)) {
            throw new InvalidPathException("File composer.json not found, '$composerJsonPath' is not a file.");
        }

        $composerJsonRawData = file_get_contents($composerJsonPath);

        if ($composerJsonRawData === false) {
            throw new InvalidPathException("Failure while reading $composerJsonPath file.");
        }

        $composerJsonData = json_decode($composerJsonRawData, true);

        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            throw new InvalidPathException("Failure while parsing $composerJsonPath file: " . json_last_error_msg());
        }

        return $composerJsonData; // @phpstan-ignore-line ignore mixed returned
    }

}
