<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;

class ShadowDependencyDetectorTest extends TestCase
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
        $detector = new ShadowDependencyDetector(
            $vendorDir,
            $classmap,
            $dependencies
        );
        $result = $detector->scan([__DIR__ . '/data/shadow-dependencies.php']);

        self::assertSame([
            "Regular\Package not found in classmap (precondition violated?)\n",
            "Shadow\Package\Clazz used as shadow dependency!\n",
        ], $result);
    }

}
