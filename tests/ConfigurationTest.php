<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedClassIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidConfigException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use function realpath;
use const DIRECTORY_SEPARATOR;

class ConfigurationTest extends TestCase
{

    public function testShouldIgnore(): void
    {
        $configuration = new Configuration();
        $configuration->ignoreUnknownClasses(['Unknown\Clazz']);
        $configuration->ignoreErrors([ErrorType::UNUSED_DEPENDENCY, ErrorType::UNKNOWN_CLASS]);
        $configuration->ignoreErrorsOnPath(__DIR__ . '/app/../', [ErrorType::SHADOW_DEPENDENCY]);
        $configuration->ignoreErrorsOnPackage('my/package', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
        $configuration->ignoreErrorsOnPackageAndPath('vendor/package', __DIR__ . '/../tests/app', [ErrorType::DEV_DEPENDENCY_IN_PROD]);

        $ignoreList = $configuration->getIgnoreList();

        self::assertTrue($ignoreList->shouldIgnoreUnknownClass('Unknown\Clazz', __DIR__));
        self::assertTrue($ignoreList->shouldIgnoreUnknownClass('Unknown\Clazz', __DIR__ . '/app'));
        self::assertTrue($ignoreList->shouldIgnoreUnknownClass('Any\Clazz', __DIR__));

        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, null, null));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, null, 'some/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, __DIR__, null));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::UNUSED_DEPENDENCY, __DIR__, 'some/package'));

        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, null, null));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, null, 'some/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__, null));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'some/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__ . DIRECTORY_SEPARATOR . 'app', null));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__ . DIRECTORY_SEPARATOR . 'app', 'some/package'));

        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, null, null));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, null, 'some/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, null, 'my/package'));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, __DIR__, null));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, __DIR__, 'some/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV, __DIR__, 'my/package'));

        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, __DIR__, null));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, __DIR__ . DIRECTORY_SEPARATOR . 'app', null));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, __DIR__ . DIRECTORY_SEPARATOR . 'app', 'wrong/package'));
        self::assertFalse($ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, __DIR__ . DIRECTORY_SEPARATOR . 'data', 'vendor/package'));
        self::assertTrue($ignoreList->shouldIgnoreError(ErrorType::DEV_DEPENDENCY_IN_PROD, __DIR__ . DIRECTORY_SEPARATOR . 'app', 'vendor/package'));
    }

    public function testOverlappingUnusedIgnores(): void
    {
        $configuration = new Configuration();
        $configuration->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
        $configuration->ignoreErrorsOnPath(__DIR__, [ErrorType::SHADOW_DEPENDENCY]);
        $configuration->ignoreErrorsOnPackage('vendor/package', [ErrorType::SHADOW_DEPENDENCY]);
        $configuration->ignoreErrorsOnPackageAndPath('vendor/package', __DIR__, [ErrorType::SHADOW_DEPENDENCY]);

        $ignoreList1 = $configuration->getIgnoreList();
        $ignoreList2 = $configuration->getIgnoreList();
        $ignoreList3 = $configuration->getIgnoreList();

        foreach ([$ignoreList1, $ignoreList2, $ignoreList3] as $ignoreList) {
            self::assertEquals([
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, __DIR__, null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, 'vendor/package'),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'vendor/package'),
            ], $ignoreList->getUnusedIgnores());
        }

        self::assertTrue($ignoreList1->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'vendor/package'));
        self::assertEquals([], $ignoreList1->getUnusedIgnores());

        self::assertTrue($ignoreList2->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, realpath(__DIR__ . '/..'), 'vendor/package')); // @phpstan-ignore-line realpath wont fail here
        self::assertEquals([
            new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, __DIR__, null),
            new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'vendor/package'),
        ], $ignoreList2->getUnusedIgnores());

        self::assertTrue($ignoreList3->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'another/package'));
        self::assertEquals([
            new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, 'vendor/package'),
            new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, __DIR__, 'vendor/package'),
        ], $ignoreList3->getUnusedIgnores());
    }

    public function testOverlappingUnusedIgnoresOfUnknownClass(): void
    {
        $configuration = new Configuration();
        $configuration->ignoreErrors([ErrorType::UNKNOWN_CLASS]);
        $configuration->ignoreErrorsOnPath(__DIR__, [ErrorType::UNKNOWN_CLASS]);
        $configuration->ignoreUnknownClasses(['Unknown\Clazz']);
        $configuration->ignoreUnknownClassesRegex('~^Unknown~');

        $ignoreList1 = $configuration->getIgnoreList();
        $ignoreList2 = $configuration->getIgnoreList();
        $ignoreList3 = $configuration->getIgnoreList();

        $parentDir = realpath(__DIR__ . '/..');
        self::assertNotFalse($parentDir);

        foreach ([$ignoreList1, $ignoreList2, $ignoreList3] as $ignoreList) {
            self::assertEquals([
                new UnusedErrorIgnore(ErrorType::UNKNOWN_CLASS, null, null),
                new UnusedErrorIgnore(ErrorType::UNKNOWN_CLASS, __DIR__, null),
                new UnusedClassIgnore('Unknown\Clazz', false),
                new UnusedClassIgnore('~^Unknown~', true),
            ], $ignoreList->getUnusedIgnores());
        }

        self::assertTrue($ignoreList1->shouldIgnoreUnknownClass('Unknown\Clazz', __DIR__));
        self::assertEquals([], $ignoreList1->getUnusedIgnores());

        self::assertTrue($ignoreList2->shouldIgnoreUnknownClass('Unknown\Clazz', $parentDir));
        self::assertEquals([
            new UnusedErrorIgnore(ErrorType::UNKNOWN_CLASS, __DIR__, null),
        ], $ignoreList2->getUnusedIgnores());

        self::assertTrue($ignoreList3->shouldIgnoreUnknownClass('Another\Clazz', $parentDir));
        self::assertEquals([
            new UnusedErrorIgnore(ErrorType::UNKNOWN_CLASS, __DIR__, null),
            new UnusedClassIgnore('Unknown\Clazz', false),
            new UnusedClassIgnore('~^Unknown~', true),
        ], $ignoreList3->getUnusedIgnores());
    }

    /**
     * @param callable(Configuration): void $configure
     * @dataProvider provideInvalidConfigs
     */
    public function testInvalidConfig(callable $configure, string $exceptionMessage): void
    {
        $configuration = new Configuration();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $configure($configuration);
    }

    /**
     * @return iterable<string, array{callable(Configuration): void, string}>
     */
    public function provideInvalidConfigs(): iterable
    {
        yield 'invalid ignore for path #1' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPath(__DIR__, [ErrorType::SHADOW_DEPENDENCY, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNUSED_DEPENDENCY errors cannot be ignored on a path',
        ];

        yield 'invalid ignore for path #2' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPaths([__DIR__], [ErrorType::SHADOW_DEPENDENCY, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNUSED_DEPENDENCY errors cannot be ignored on a path',
        ];

        yield 'invalid ignore for path #3' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackageAndPath('my/package', __DIR__, [ErrorType::SHADOW_DEPENDENCY, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNUSED_DEPENDENCY errors cannot be ignored on a path',
        ];

        yield 'invalid ignore for path #4' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackagesAndPaths(['my/package'], [__DIR__], [ErrorType::SHADOW_DEPENDENCY, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNUSED_DEPENDENCY errors cannot be ignored on a path',
        ];

        yield 'invalid ignore for package #1' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackage('my/package', [ErrorType::UNKNOWN_CLASS, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNKNOWN_CLASS errors cannot be ignored on a package',
        ];

        yield 'invalid ignore for package #2' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackages(['my/package'], [ErrorType::UNKNOWN_CLASS, ErrorType::UNUSED_DEPENDENCY]);
            },
            'UNKNOWN_CLASS errors cannot be ignored on a package',
        ];

        yield 'invalid ignore for package #3' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackageAndPaths('my/package', [__DIR__], [ErrorType::UNKNOWN_CLASS]);
            },
            'UNKNOWN_CLASS errors cannot be ignored on a package',
        ];

        yield 'invalid ignore for package #4' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackagesAndPaths(['my/package'], [__DIR__], [ErrorType::UNKNOWN_CLASS]);
            },
            'UNKNOWN_CLASS errors cannot be ignored on a package',
        ];

        yield 'invalid package name #1' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackage('src', [ErrorType::SHADOW_DEPENDENCY]);
            },
            "Invalid package name 'src'",
        ];

        yield 'invalid package name #2' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackageAndPath('src', __DIR__, [ErrorType::SHADOW_DEPENDENCY]);
            },
            "Invalid package name 'src'",
        ];

        yield 'invalid package name #3' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackages(['src'], [ErrorType::SHADOW_DEPENDENCY]);
            },
            "Invalid package name 'src'",
        ];

        yield 'invalid package name #4' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackagesAndPaths(['src'], [__DIR__], [ErrorType::SHADOW_DEPENDENCY]);
            },
            "Invalid package name 'src'",
        ];

        yield 'invalid regex' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreUnknownClassesRegex('~[~');
            },
            "Invalid regex '~[~'",
        ];
    }

    /**
     * @param callable(Configuration): void $configure
     * @dataProvider provideInvalidPaths
     */
    public function testInvalidPath(callable $configure, string $exceptionMessage): void
    {
        $configuration = new Configuration();
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $configure($configuration);
    }

    /**
     * @return iterable<string, array{callable(Configuration): void, string}>
     */
    public function provideInvalidPaths(): iterable
    {
        yield 'invalid path' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPath('app', [ErrorType::SHADOW_DEPENDENCY]);
            },
            "'app' is not a file nor directory",
        ];

        yield 'invalid path #2' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPaths(['app'], [ErrorType::SHADOW_DEPENDENCY]);
            },
            "'app' is not a file nor directory",
        ];

        yield 'invalid path #3' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackageAndPath('my/package', 'app', [ErrorType::SHADOW_DEPENDENCY]);
            },
            "'app' is not a file nor directory",
        ];

        yield 'invalid path #4' => [
            static function (Configuration $configuration): void {
                $configuration->ignoreErrorsOnPackagesAndPaths(['my/package'], ['app'], [ErrorType::SHADOW_DEPENDENCY]);
            },
            "'app' is not a file nor directory",
        ];
    }

}
