<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use function dirname;
use function file_put_contents;
use function json_encode;
use function realpath;
use function sys_get_temp_dir;

class ComposerJsonTest extends TestCase
{

    public function testComposerJson(): void
    {
        $composerJsonPath = __DIR__ . '/data/not-autoloaded/composer/sample.json';
        $composerJson = new ComposerJson($composerJsonPath);

        self::assertSame(
            dirname($composerJsonPath) . '/custom-vendor/autoload.php',
            $composerJson->composerAutoloadPath
        );

        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );

        self::assertSame(
            [
                realpath(__DIR__ . '/data/not-autoloaded/composer/dir2/file1.php') => false,
                realpath(__DIR__ . '/data/not-autoloaded/composer/dir1') => false,
                realpath(__DIR__ . '/data/not-autoloaded/composer/dir2') => false,
            ],
            $composerJson->autoloadPaths
        );
    }

    public function testAbsoluteCustomVendorDir(): void
    {
        $generatedComposerJson = sys_get_temp_dir() . '/custom-vendor.json';
        file_put_contents($generatedComposerJson, json_encode([
            'require' => [
                'nette/utils' => '^3.0',
            ],
            'config' => [
                'vendor-dir' => sys_get_temp_dir(),
            ],
        ]));

        $composerJson = new ComposerJson($generatedComposerJson);

        self::assertSame(
            sys_get_temp_dir() . '/autoload.php',
            $composerJson->composerAutoloadPath
        );
    }

}
