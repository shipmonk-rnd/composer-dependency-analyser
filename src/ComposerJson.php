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
use function is_file;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function str_replace;
use function strpos;
use function strtr;
use function trim;
use const ARRAY_FILTER_USE_KEY;
use const JSON_ERROR_NONE;

class ComposerJson
{

    /**
     * @readonly
     * @var string
     */
    public $composerAutoloadPath;

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
     * Regex => isDev
     *
     * @readonly
     * @var array<string, bool>
     */
    public $autoloadExcludeRegexes;

    /**
     * @throws InvalidPathException
     * @throws InvalidConfigException
     */
    public function __construct(
        string $composerJsonPath
    )
    {
        $basePath = dirname($composerJsonPath);

        $composerJsonData = $this->parseComposerJson($composerJsonPath);
        $this->composerAutoloadPath = $this->resolveComposerAutoloadPath($basePath, $composerJsonData['config']['vendor-dir'] ?? 'vendor');

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
        $this->autoloadExcludeRegexes = array_merge(
            $this->extractAutoloadExcludeRegexes($basePath, $composerJsonData['autoload']['exclude-from-classmap'] ?? [], false),
            $this->extractAutoloadExcludeRegexes($basePath, $composerJsonData['autoload-dev']['exclude-from-classmap'] ?? [], true)
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
                        $result[Path::normalize($globPath)] = $isDev;
                    }

                    continue;
                }

                $result[Path::normalize($absolutePath)] = $isDev;
            }
        }

        return $result;
    }

    /**
     * @param array<string> $exclude
     * @return array<string, bool>
     * @throws InvalidPathException
     */
    private function extractAutoloadExcludeRegexes(string $basePath, array $exclude, bool $isDev): array
    {
        $regexes = [];

        foreach ($exclude as $path) {
            $regexes[$this->resolveAutoloadExclude($basePath, $path)] = $isDev;
        }

        return $regexes;
    }

    /**
     * Implementation copied from composer/composer.
     *
     * @license MIT https://github.com/composer/composer/blob/ee2c9afdc86ef3f06a4bd49b1fea7d1d636afc92/LICENSE
     * @see https://getcomposer.org/doc/04-schema.md#exclude-files-from-classmaps
     * @see https://github.com/composer/composer/blob/ee2c9afdc86ef3f06a4bd49b1fea7d1d636afc92/src/Composer/Autoload/AutoloadGenerator.php#L1256-L1286
     * @throws InvalidPathException
     */
    private function resolveAutoloadExclude(string $basePath, string $pathPattern): string
    {
        // first escape user input
        $path = preg_replace('{/+}', '/', preg_quote(trim(strtr($pathPattern, '\\', '/'), '/')));

        if ($path === null) {
            throw new InvalidPathException("Failure while globbing $pathPattern path.");
        }

        // add support for wildcards * and **
        $path = strtr($path, ['\\*\\*' => '.+?', '\\*' => '[^/]+?']);

        // add support for up-level relative paths
        $updir = null;
        $path = preg_replace_callback(
            '{^((?:(?:\\\\\\.){1,2}+/)+)}',
            static function ($matches) use (&$updir): string {
                if (isset($matches[1]) && $matches[1] !== '') {
                    // undo preg_quote for the matched string
                    $updir = str_replace('\\.', '.', $matches[1]);
                }

                return '';
            },
            $path
            // note: composer also uses `PREG_UNMATCHED_AS_NULL` but the `$flags` arg supported since PHP v7.4
        );

        if ($path === null) {
            throw new InvalidPathException("Failure while globbing $pathPattern path.");
        }

        $resolvedPath = realpath($basePath . '/' . $updir);

        if ($resolvedPath === false) {
            throw new InvalidPathException("Failure while globbing $pathPattern path.");
        }

        // Finalize
        $delimiter = '#';
        $pattern = '^' . preg_quote(strtr($resolvedPath, '\\', '/'), $delimiter) . '/' . $path . '($|/)';
        $pattern = $delimiter . $pattern . $delimiter;

        return $pattern;
    }

    /**
     * @return array{
     *     require?: array<string, string>,
     *     require-dev?: array<string, string>,
     *     config?: array{
     *         vendor-dir?: string,
     *     },
     *     autoload?: array{
     *          psr-0?: array<string, string|string[]>,
     *          psr-4?: array<string, string|string[]>,
     *          files?: string[],
     *          classmap?: string[],
     *          exclude-from-classmap?: string[]
     *     },
     *     autoload-dev?: array{
     *          psr-0?: array<string, string|string[]>,
     *          psr-4?: array<string, string|string[]>,
     *          files?: string[],
     *          classmap?: string[],
     *          exclude-from-classmap?: string[]
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

    private function resolveComposerAutoloadPath(string $basePath, string $vendorDir): string
    {
        if (Path::isAbsolute($vendorDir)) {
            return Path::normalize($vendorDir . '/autoload.php');
        }

        return Path::normalize($basePath . '/' . $vendorDir . '/autoload.php');
    }

}
