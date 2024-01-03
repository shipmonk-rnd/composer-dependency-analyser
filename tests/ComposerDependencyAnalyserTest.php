<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\UnusedDependencyError;

class ComposerDependencyAnalyserTest extends TestCase
{

    public function test(): void
    {
        $appDir = __DIR__ . '/app';
        $vendorDir = __DIR__ . '/vendor';
        $classmap = [
            'Regular\Package\Clazz' => $vendorDir . '/regular/package/Clazz.php',
            'Shadow\Package\Clazz' => $vendorDir . '/shadow/package/Clazz.php',
            'Dev\Package\Clazz' => $vendorDir . '/dev/package/Clazz.php',
            'App\Clazz' => $appDir . '/Clazz.php',
        ];
        $dependencies = [
            'regular/package' => false,
            'dev/package' => true,
            'regular/dead' => false,
            'dev/dead' => true,
        ];
        $detector = new ComposerDependencyAnalyser(
            $vendorDir,
            $classmap,
            $dependencies
        );
        $scanPath = __DIR__ . '/data/shadow-dependencies.php';
        $result = $detector->scan([$scanPath => false]);

        self::assertEquals([
            new ClassmapEntryMissingError(new ClassUsage('Unknown\Clazz', $scanPath, 11)),
            new DevDependencyInProductionCodeError('dev/package', new ClassUsage('Dev\Package\Clazz', $scanPath, 16)),
            new ShadowDependencyError('shadow/package', new ClassUsage('Shadow\Package\Clazz', $scanPath, 15)),
            new UnusedDependencyError('regular/dead'),
        ], $result);
    }

}
