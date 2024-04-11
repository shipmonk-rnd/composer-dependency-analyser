<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use function file_get_contents;
use const PHP_VERSION_ID;

class UsedSymbolExtractorTest extends TestCase
{

    /**
     * @param array<SymbolKind::*, array<string, list<int>>> $expectedUsages
     * @dataProvider provideVariants
     */
    public function test(string $path, array $expectedUsages): void
    {
        $code = file_get_contents($path);
        self::assertNotFalse($code);

        $extractor = new UsedSymbolExtractor($code);

        self::assertSame($expectedUsages, $extractor->parseUsedSymbols());
    }

    /**
     * @return iterable<array{string, array<SymbolKind::*, array<string, list<int>>>}>
     */
    public function provideVariants(): iterable
    {
        yield 'use statements' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/use-statements.php',
            [
                SymbolKind::CLASSLIKE => [
                    'PHPUnit\Framework\Exception' => [11],
                    'PHPUnit\Framework\Warning' => [12],
                    'PHPUnit\Framework\Error' => [13],
                    'PHPUnit\Framework\OutputError' => [14],
                    'PHPUnit\Framework\Constraint\IsNan' => [15],
                    'PHPUnit\Framework\Constraint\IsFinite' => [16],
                    'PHPUnit\Framework\Constraint\DirectoryExists' => [17],
                    'PHPUnit\Framework\Constraint\FileExists' => [18],
                ],
                SymbolKind::FUNCTION => [
                    'PHPUnit\Framework\assertArrayNotHasKey' => [36],
                    'PHPUnit\Framework\assertArrayHasKey' => [37],
                    'PHPUnit\Framework\assertEquals' => [38],
                ],
            ]
        ];

        yield 'various usages' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/various-usages.php',
            [
                SymbolKind::CLASSLIKE => [
                    'DateTimeImmutable' => [12],
                    'DateTimeInterface' => [12],
                    'DateTime' => [12],
                    'PHPUnit\Framework\Error' => [14],
                    'LogicException' => [15, 20],
                ],
            ]
        ];

        yield 'bracket namespace' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/bracket-namespace.php',
            [
                SymbolKind::CLASSLIKE => [
                    'DateTimeImmutable' => [5],
                    'DateTime' => [11],
                ],
            ]
        ];

        yield 'other symbols' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/other-symbols.php',
            [
                SymbolKind::CONSTANT => [
                    'PHP_EOL' => [9],
                ],
                SymbolKind::CLASSLIKE => [
                    'DIRECTORY_SEPARATOR' => [10],
                ],
                SymbolKind::FUNCTION => [
                    'strlen' => [12],
                    'strpos' => [13],
                    'PHPUnit\Framework\assertArrayHasKey' => [14],
                    'PHPUnit\Framework\assertArrayNotHasKey' => [15],
                ],
            ]
        ];

        yield 'relative namespace' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/relative-namespace.php',
            [
                SymbolKind::CLASSLIKE => [
                    'DateTimeImmutable' => [10],
                ],
            ],
        ];

        yield 'global namespace' => [

            __DIR__ . '/data/not-autoloaded/used-symbols/global-namespace.php',
            [
                SymbolKind::CLASSLIKE => [
                    'DateTimeImmutable' => [3],
                ],
            ],
        ];

        yield 'curly braces' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/curly-braces.php',
            []
        ];

        yield 'attribute' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/attribute.php',
            PHP_VERSION_ID >= 80000
                ? [
                    SymbolKind::CLASSLIKE => [
                        'SomeAttribute' => [3],
                    ],
                ]
                : []
        ];
    }

}
