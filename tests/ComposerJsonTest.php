<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;

class ComposerJsonTest extends TestCase
{

    public function testComposerJson(): void
    {
        $composerJson = new ComposerJson([
            'require' => [
                'php' => '^8.0',
                'nette/utils' => '^3.0',
            ],
            'require-dev' => [
                'phpstan/phpstan' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
                'files' => [
                    'public/bootstrap.php',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'App\\' => ['build/', 'tests/'],
                ],
            ],
        ]);

        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );

        self::assertSame(
            [
                'src/' => false,
                'public/bootstrap.php' => false,
                'build/' => true,
                'tests/' => true,
            ],
            $composerJson->autoloadPaths
        );
    }

}
