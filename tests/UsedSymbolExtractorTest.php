<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use function file_get_contents;

class UsedSymbolExtractorTest extends TestCase
{

    /**
     * @param array<string, list<int>> $expectedUsages
     * @dataProvider provideVariants
     */
    public function test(string $path, array $expectedUsages): void
    {
        $code = file_get_contents($path);
        self::assertNotFalse($code);

        $extractor = new UsedSymbolExtractor($code);

        self::assertSame($expectedUsages, $extractor->parseUsedClasses());
    }

    /**
     * @return iterable<array{string, array<string, list<int>>}>
     */
    public function provideVariants(): iterable
    {
        yield 'use statements' => [
            __DIR__ . '/data/used-symbols/use-statements.php',
            [
                'PHPUnit\Framework\Exception' => [11],
                'PHPUnit\Framework\Warning' => [12],
                'PHPUnit\Framework\Error' => [13],
                'PHPUnit\Framework\OutputError' => [14],
                'PHPUnit\Framework\Constraint\IsNan' => [15],
                'PHPUnit\Framework\Constraint\IsFinite' => [16],
                'PHPUnit\Framework\Constraint\DirectoryExists' => [17],
                'PHPUnit\Framework\Constraint\FileExists' => [18],
            ]
        ];

        yield 'various usages' => [
            __DIR__ . '/data/used-symbols/various-usages.php',
            [
                'DateTimeImmutable' => [12],
                'DateTimeInterface' => [12],
                'DateTime' => [12],
                'PHPUnit\Framework\Error' => [14],
                'LogicException' => [15, 20],
            ]
        ];

        yield 'bracket namespace' => [
            __DIR__ . '/data/used-symbols/bracket-namespace.php',
            [
                'DateTimeImmutable' => [5],
                'DateTime' => [11],
            ]
        ];

        yield 'other symbols' => [
            __DIR__ . '/data/used-symbols/other-symbols.php',
            [
                'DIRECTORY_SEPARATOR' => [9],
                'strlen' => [11],
            ]
        ];

        yield 'relative namespace' => [
            __DIR__ . '/data/used-symbols/relative-namespace.php',
            [
                'DateTimeImmutable' => [10],
            ]
        ];

        yield 'global namespace' => [
            __DIR__ . '/data/used-symbols/global-namespace.php',
            [
                'DateTimeImmutable' => [3],
            ]
        ];
    }

}
