<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use function file_get_contents;

class UsedSymbolExtractorTest extends TestCase
{

    /**
     * @param list<string> $expectedUsages
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
     * @return iterable<array{string, list<string>}>
     */
    public function provideVariants(): iterable
    {
        yield 'use statements' => [
            __DIR__ . '/data/used-symbols/use-statements.php',
            [
                'PHPUnit\Framework\Exception',
                'PHPUnit\Framework\Warning',
                'PHPUnit\Framework\Error',
                'PHPUnit\Framework\OutputError',
                'PHPUnit\Framework\Constraint\IsNan',
                'PHPUnit\Framework\Constraint\IsFinite',
                'PHPUnit\Framework\Constraint\DirectoryExists',
                'PHPUnit\Framework\Constraint\FileExists',
            ]
        ];

        yield 'various usages' => [
            __DIR__ . '/data/used-symbols/various-usages.php',
            [
                'DateTimeImmutable',
                'DateTimeInterface',
                'DateTime',
                'PHPUnit\Framework\Error',
                'LogicException',
            ]
        ];

        yield 'bracket namespace' => [
            __DIR__ . '/data/used-symbols/bracket-namespace.php',
            [
                'DateTimeImmutable',
                'DateTime',
            ]
        ];

        yield 'other symbols' => [
            __DIR__ . '/data/used-symbols/other-symbols.php',
            [
                'DIRECTORY_SEPARATOR',
                'strlen',
            ]
        ];

        yield 'relative namespace' => [
            __DIR__ . '/data/used-symbols/relative-namespace.php',
            [
                'DateTimeImmutable',
            ]
        ];
    }

}
