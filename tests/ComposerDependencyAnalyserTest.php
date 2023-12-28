<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\ShadowDependencyError;

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
        ];
        $detector = new ComposerDependencyAnalyser(
            $vendorDir,
            $classmap,
            $dependencies
        );
        $scanPath = __DIR__ . '/data/shadow-dependencies.php';
        $result = $detector->scan([$scanPath]);

        self::assertEquals([
            'Regular\Package' => new ClassmapEntryMissingError('Regular\Package', $scanPath),
            'Shadow\Package\Clazz' => new ShadowDependencyError('Shadow\Package\Clazz', 'shadow/package', $scanPath),
        ], $result);
    }

}
