<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;

class ComposerJsonTest extends TestCase
{

    public function testComposerJson(): void
    {
        $composerJson = new ComposerJson(__DIR__ . '/composer/sample.json');

        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );

        self::assertSame(
            [
                __DIR__ . '/composer/dir2/file1.php' => false,
                __DIR__ . '/composer/dir1' => false,
                __DIR__ . '/composer/dir2' => false,
            ],
            $composerJson->autoloadPaths
        );
    }

}
