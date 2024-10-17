<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function strtolower;
use const PHP_VERSION_ID;

class UsedSymbolExtractorTest extends TestCase
{

    /**
     * @param array<SymbolKind::*, array<string, list<int>>> $expectedUsages
     * @param array<string, SymbolKind::*> $extensionSymbols
     * @dataProvider provideVariants
     */
    public function test(string $path, array $expectedUsages, array $extensionSymbols = []): void
    {
        $code = file_get_contents($path);
        self::assertNotFalse($code);

        $extractor = new UsedSymbolExtractor($code);

        self::assertSame(
            $expectedUsages,
            $extractor->parseUsedSymbols($extensionSymbols)
        );
    }

    /**
     * @return iterable<array{0: string, 1: array<SymbolKind::*, array<string, list<int>>>, 2?: array<string, SymbolKind::*>}>
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
            ],
        ];

        yield 'T_STRING issues' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/t-string-issues.php',
            [],
            [
                strtolower('PDO') => SymbolKind::CLASSLIKE,
            ],
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
            ],
        ];

        yield 'bracket namespace' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/bracket-namespace.php',
            [
                SymbolKind::CLASSLIKE => [
                    'DateTimeImmutable' => [5],
                    'DateTime' => [11],
                ],
            ],
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
            ],
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
                    'PHPUnit\Framework\Error' => [5],
                ],
                SymbolKind::FUNCTION => [
                    'PHPUnit\Framework\assertSame' => [7],
                ],
            ],
        ];

        yield 'curly braces' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/curly-braces.php',
            [],
        ];

        yield 'extensions' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/extensions.php',
            [
                SymbolKind::FUNCTION => [
                    'json_encode' => [8],
                    'DDTrace\active_span' => [12],
                    'DDTrace\root_span' => [13],
                    'DDTrace\Integrations\Exec\proc_get_pid' => [16],
                    'json_decode' => [21],
                ],
                SymbolKind::CONSTANT => [
                    'LIBXML_ERR_FATAL' => [9],
                    'LIBXML_ERR_ERROR' => [10],
                    'DDTrace\DBM_PROPAGATION_FULL' => [14],
                ],
                SymbolKind::CLASSLIKE => [
                    'PDO' => [11],
                    'My\App\XMLReader' => [15],
                    'CURLOPT_SSL_VERIFYHOST' => [19],
                ],
            ],
            self::extensionSymbolsForExtensionsTestCases(),
        ];

        yield 'extensions global' => [
            __DIR__ . '/data/not-autoloaded/used-symbols/extensions-global.php',
            [
                SymbolKind::FUNCTION => [
                    'json_encode' => [8],
                    'DDTrace\active_span' => [12],
                    'DDTrace\root_span' => [13],
                    'DDTrace\Integrations\Exec\proc_get_pid' => [16],
                    'json_decode' => [21],
                ],
                SymbolKind::CONSTANT => [
                    'LIBXML_ERR_FATAL' => [9],
                    'LIBXML_ERR_ERROR' => [10],
                    'DDTrace\DBM_PROPAGATION_FULL' => [14],
                ],
                SymbolKind::CLASSLIKE => [
                    'PDO' => [11],
                    'My\App\XMLReader' => [15],
                    'CURLOPT_SSL_VERIFYHOST' => [19],
                ],
            ],
            self::extensionSymbolsForExtensionsTestCases(),
        ];

        if (PHP_VERSION_ID >= 80000) {
            yield 'attribute' => [
                __DIR__ . '/data/not-autoloaded/used-symbols/attribute.php',
                [
                    SymbolKind::CLASSLIKE => [
                        'SomeAttribute' => [3],
                        'Assert\NotNull' => [7],
                        'Assert\NotBlank' => [8],
                    ],
                ],
            ];
        }

        if (PHP_VERSION_ID >= 80400) {
            yield 'property hooks' => [
                __DIR__ . '/data/not-autoloaded/used-symbols/property-hooks.php',
                [],
            ];
        }
    }

    /**
     * @return array<string, SymbolKind::*>
     */
    private static function extensionSymbolsForExtensionsTestCases(): array
    {
        return [
            strtolower('XMLReader') => SymbolKind::CLASSLIKE,
            strtolower('PDO') => SymbolKind::CLASSLIKE,
            strtolower('json_encode') => SymbolKind::FUNCTION,
            strtolower('DDTrace\active_span') => SymbolKind::FUNCTION,
            strtolower('DDTrace\root_span') => SymbolKind::FUNCTION,
            strtolower('LIBXML_ERR_FATAL') => SymbolKind::CONSTANT,
            strtolower('LIBXML_ERR_ERROR') => SymbolKind::CONSTANT,
            strtolower('DDTrace\DBM_PROPAGATION_FULL') => SymbolKind::CONSTANT,
            strtolower('DDTrace\Integrations\Exec\proc_get_pid') => SymbolKind::FUNCTION,
        ];
    }

}
