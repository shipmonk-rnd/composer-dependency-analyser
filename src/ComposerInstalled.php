<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use function array_diff_key;
use function is_array;
use function is_file;
use function is_string;

/**
 * Composer's resolved installation state, as dumped into vendor/composer/installed.php
 *
 * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
 */
class ComposerInstalled
{

    /**
     * package name => true
     *
     * @var array<string, true>
     */
    private array $packagesInstallingNoFiles;

    /**
     * @param list<string> $vendorDirs
     */
    public function __construct(array $vendorDirs)
    {
        $installingNoFiles = [];
        $installingFiles = [];

        foreach ($vendorDirs as $vendorDir) {
            foreach ($this->readVersions($vendorDir) as $packageName => $packageData) {
                if (!is_string($packageName) || !is_array($packageData)) {
                    continue;
                }

                // virtual packages (e.g. psr/log-implementation) have no package of their own,
                // metapackages (e.g. roave/security-advisories) have one, but it ships no files
                $isVirtual = isset($packageData['provided']);
                $isMetapackage = ($packageData['type'] ?? null) === 'metapackage';

                if ($isVirtual || $isMetapackage) {
                    $installingNoFiles[$packageName] = true;
                } else {
                    $installingFiles[$packageName] = true;
                }
            }
        }

        // the same name may be virtual in one vendor dir, but really installed in another
        $this->packagesInstallingNoFiles = array_diff_key($installingNoFiles, $installingFiles);
    }

    /**
     * Whether the package is known to ship no files at all, and thus can never be used from code.
     * False for packages missing from installed.php, those are simply not installed.
     */
    public function installsNoFiles(string $packageName): bool
    {
        return isset($this->packagesInstallingNoFiles[$packageName]);
    }

    /**
     * @return array<mixed, mixed>
     */
    private function readVersions(string $vendorDir): array
    {
        $installedPath = $vendorDir . '/composer/installed.php';

        if (!is_file($installedPath)) {
            return []; // composer 1 or dependencies not installed at all
        }

        $installed = require $installedPath;

        if (!is_array($installed)) {
            return [];
        }

        $versions = $installed['versions'] ?? null;

        return is_array($versions) ? $versions : [];
    }

}
