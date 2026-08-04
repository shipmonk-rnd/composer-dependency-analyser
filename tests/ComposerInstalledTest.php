<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;

class ComposerInstalledTest extends TestCase
{

    private const FIXTURE_DIR = __DIR__ . '/data/not-autoloaded/composer-installed';

    public function testVirtualPackageInstallsNoFiles(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);

        self::assertTrue($installed->installsNoFiles('psr/log-implementation'));
    }

    public function testMetapackageInstallsNoFiles(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);

        self::assertTrue($installed->installsNoFiles('some/metapackage'));
    }

    public function testRegularPackageInstallsFiles(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);

        self::assertFalse($installed->installsNoFiles('regular/package'));
    }

    /**
     * Replaced packages do ship files, they just live in the replacing package
     */
    public function testReplacedPackageInstallsFiles(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);

        self::assertFalse($installed->installsNoFiles('illuminate/log'));
    }

    public function testUninstalledPackageIsNotReportedAsInstallingNoFiles(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);

        self::assertFalse($installed->installsNoFiles('never/heard-of-it'));
    }

    public function testMissingInstalledPhpIsTolerated(): void
    {
        $installed = new ComposerInstalled([self::FIXTURE_DIR]);

        self::assertFalse($installed->installsNoFiles('psr/log-implementation'));
        self::assertFalse($installed->installsNoFiles('some/metapackage'));
    }

    public function testPackageInstalledInAnotherVendorDirInstallsFiles(): void
    {
        $onlyProvided = new ComposerInstalled([self::FIXTURE_DIR . '/vendor']);
        self::assertTrue($onlyProvided->installsNoFiles('provided/elsewhere'));

        $alsoInstalled = new ComposerInstalled([
            self::FIXTURE_DIR . '/vendor',
            self::FIXTURE_DIR . '/other-vendor',
        ]);
        self::assertFalse($alsoInstalled->installsNoFiles('provided/elsewhere'));
    }

}
