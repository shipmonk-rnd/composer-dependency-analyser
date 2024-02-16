<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use function realpath;

class ComposerJsonTest extends TestCase
{

    public function testComposerJson(): void
    {
        $composerJson = new ComposerJson(__DIR__ . '/data/composer/sample.json');

        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );

        self::assertSame(
            [
                realpath(__DIR__ . '/data/composer/dir2/file1.php') => false,
                realpath(__DIR__ . '/data/composer/dir1') => false,
                realpath(__DIR__ . '/data/composer/dir2') => false,
            ],
            $composerJson->autoloadPaths
        );
    }

}
