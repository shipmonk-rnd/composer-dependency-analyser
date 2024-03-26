<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Composer\InstalledVersions;
use OutOfBoundsException;
use function realpath;

class InstallPathDetector
{

    public function getRealInstallPath(string $packageName): ?string
    {
        try {
            /** @throws OutOfBoundsException */
            $installPath = InstalledVersions::getInstallPath($packageName);

            if ($installPath === null) {
                return null;
            }

            $realInstallPath = realpath($installPath);

            if ($realInstallPath === false) {
                return null;
            }

            return $realInstallPath;

        } catch (OutOfBoundsException $e) {
            return null;
        }
    }

}
