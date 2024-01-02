<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use function array_fill_keys;
use function array_filter;
use function array_keys;
use function array_merge;
use function is_array;
use function strpos;
use const ARRAY_FILTER_USE_KEY;

class ComposerJson
{

    /**
     * Path => isDev
     *
     * @readonly
     * @var array<string, bool>
     */
    public array $dependencies;

    /**
     * Path => isDev
     *
     * @readonly
     * @var array<string, bool>
     */
    public array $autoloadPaths;

    /**
     * @param array{require?: array<string, string>, require-dev?: array<string, string>, autoload?: array{psr-0?: array<string, string|string[]>, psr-4?: array<string, string|string[]>, files?: string[]}, autoload-dev?: array{psr-0?: array<string, string|string[]>, psr-4?: array<string, string|string[]>, files?: string[]}} $composerJsonData
     */
    public function __construct(array $composerJsonData)
    {
        $requiredPackages = $composerJsonData['require'] ?? [];
        $requiredDevPackages = $composerJsonData['require-dev'] ?? [];

        $this->autoloadPaths = array_merge(
            $this->extractAutoloadPaths($composerJsonData['autoload']['psr-0'] ?? [], false),
            $this->extractAutoloadPaths($composerJsonData['autoload']['psr-4'] ?? [], false),
            $this->extractAutoloadPaths($composerJsonData['autoload']['files'] ?? [], false),
            $this->extractAutoloadPaths($composerJsonData['autoload-dev']['psr-0'] ?? [], true),
            $this->extractAutoloadPaths($composerJsonData['autoload-dev']['psr-4'] ?? [], true),
            $this->extractAutoloadPaths($composerJsonData['autoload-dev']['files'] ?? [], true),
            // classmap not supported
        );

        $filterPackages = static function (string $package): bool {
            return strpos($package, '/') !== false;
        };

        $this->dependencies = array_merge(
            array_fill_keys(array_keys(array_filter($requiredPackages, $filterPackages, ARRAY_FILTER_USE_KEY)), false),
            array_fill_keys(array_keys(array_filter($requiredDevPackages, $filterPackages, ARRAY_FILTER_USE_KEY)), true)
        );
    }

    /**
     * @param array<string|array<string>> $autoload
     * @return array<string, bool>
     */
    private function extractAutoloadPaths(array $autoload, bool $isDev): array
    {
        $result = [];

        foreach ($autoload as $paths) {
            if (!is_array($paths)) {
                $paths = [$paths];
            }

            foreach ($paths as $path) {
                $result[$path] = $isDev;
            }
        }

        return $result;
    }

}
