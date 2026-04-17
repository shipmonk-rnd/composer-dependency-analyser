<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\JsonFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\ResultFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;

class JsonFormatterTest extends FormatterTestCase
{

    public function testPrintResult(): void
    {
        $noIssuesOutput = $this->getFormatterNormalizedOutput(static function (ResultFormatter $formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [], []), new CliOptions(), new Configuration());
        });

        $expectedNoIssuesOutput = <<<'OUT'
{
    "scannedFilesCount": 2,
    "elapsedTime": 0.123,
    "usagesPerSymbolLimit": 1,
    "unknownSymbols": {
        "classes": [],
        "functions": []
    },
    "shadowDependencies": {
        "packages": []
    },
    "devDependenciesInProd": {
        "packages": []
    },
    "prodDependenciesOnlyInDev": {
        "packages": []
    },
    "unusedDependencies": {
        "packages": []
    },
    "unusedIgnores": {
        "symbols": [],
        "errors": []
    }
}

OUT;

        self::assertJsonStringEqualsJsonString($this->normalizeEol($expectedNoIssuesOutput), $noIssuesOutput);

        $analysisResult = new AnalysisResult(
            10,
            0.123,
            [],
            ['Unknown\\Thing' => [
                new SymbolUsage('/app/app/init.php', 1091, SymbolKind::CLASSLIKE),
                new SymbolUsage('/app/app/init.php', 1093, SymbolKind::CLASSLIKE),
            ]],
            ['unknown_fn' => [new SymbolUsage('/app/app/foo.php', 51, SymbolKind::FUNCTION)]],
            [
                'shadow/package' => [
                    'Shadow\\Utils' => [
                        new SymbolUsage('/app/src/Utils.php', 19, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Utils.php', 22, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Application.php', 128, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Controller.php', 229, SymbolKind::CLASSLIKE),
                    ],
                    'Shadow\\Comparator' => [new SymbolUsage('/app/src/Printer.php', 25, SymbolKind::CLASSLIKE)],
                    'Third\\Parser' => [new SymbolUsage('/app/src/bootstrap.php', 317, SymbolKind::CLASSLIKE)],
                    'Forth\\Provider' => [new SymbolUsage('/app/src/bootstrap.php', 873, SymbolKind::CLASSLIKE)],
                    'shadow_helper' => [new SymbolUsage('/app/src/helpers.php', 12, SymbolKind::FUNCTION)],
                ],
                'shadow/another' => [
                    'Another\\Controller' => [new SymbolUsage('/outside/bootstrap.php', 173, SymbolKind::CLASSLIKE)],
                ],
            ],
            ['some/package' => ['Another\\Command' => [new SymbolUsage('/app/src/ProductGenerator.php', 28, SymbolKind::CLASSLIKE)]]],
            ['misplaced/package'],
            ['dead/package'],
            [],
        );

        $regularOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($analysisResult): void {
            $formatter->format($analysisResult, new CliOptions(), new Configuration());
        });

        $expectedRegularOutput = <<<'OUT'
{
    "scannedFilesCount": 10,
    "elapsedTime": 0.123,
    "usagesPerSymbolLimit": 1,
    "unknownSymbols": {
        "classes": [
            {
                "name": "Unknown\\Thing",
                "usages": [
                    {
                        "file": "app/init.php",
                        "line": 1091
                    }
                ]
            }
        ],
        "functions": [
            {
                "name": "unknown_fn",
                "usages": [
                    {
                        "file": "app/foo.php",
                        "line": 51
                    }
                ]
            }
        ]
    },
    "shadowDependencies": {
        "packages": [
            {
                "name": "shadow/another",
                "classes": [
                    {
                        "name": "Another\\Controller",
                        "usages": [
                            {
                                "file": "/outside/bootstrap.php",
                                "line": 173
                            }
                        ]
                    }
                ],
                "functions": []
            },
            {
                "name": "shadow/package",
                "classes": [
                    {
                        "name": "Forth\\Provider",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 873
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Comparator",
                        "usages": [
                            {
                                "file": "src/Printer.php",
                                "line": 25
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Utils",
                        "usages": [
                            {
                                "file": "src/Utils.php",
                                "line": 19
                            }
                        ]
                    },
                    {
                        "name": "Third\\Parser",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 317
                            }
                        ]
                    }
                ],
                "functions": [
                    {
                        "name": "shadow_helper",
                        "usages": [
                            {
                                "file": "src/helpers.php",
                                "line": 12
                            }
                        ]
                    }
                ]
            }
        ]
    },
    "devDependenciesInProd": {
        "packages": [
            {
                "name": "some/package",
                "classes": [
                    {
                        "name": "Another\\Command",
                        "usages": [
                            {
                                "file": "src/ProductGenerator.php",
                                "line": 28
                            }
                        ]
                    }
                ],
                "functions": []
            }
        ]
    },
    "prodDependenciesOnlyInDev": {
        "packages": [
            {
                "name": "misplaced/package"
            }
        ]
    },
    "unusedDependencies": {
        "packages": [
            {
                "name": "dead/package"
            }
        ]
    },
    "unusedIgnores": {
        "symbols": [],
        "errors": []
    }
}

OUT;

        self::assertJsonStringEqualsJsonString($this->normalizeEol($expectedRegularOutput), $regularOutput);

        $verboseOptions = new CliOptions();
        $verboseOptions->verbose = true;

        $verboseOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($analysisResult, $verboseOptions): void {
            $formatter->format($analysisResult, $verboseOptions, new Configuration());
        });

        $expectedVerboseOutput = <<<'OUT'
{
    "scannedFilesCount": 10,
    "elapsedTime": 0.123,
    "usagesPerSymbolLimit": 3,
    "unknownSymbols": {
        "classes": [
            {
                "name": "Unknown\\Thing",
                "usages": [
                    {
                        "file": "app/init.php",
                        "line": 1091
                    },
                    {
                        "file": "app/init.php",
                        "line": 1093
                    }
                ]
            }
        ],
        "functions": [
            {
                "name": "unknown_fn",
                "usages": [
                    {
                        "file": "app/foo.php",
                        "line": 51
                    }
                ]
            }
        ]
    },
    "shadowDependencies": {
        "packages": [
            {
                "name": "shadow/another",
                "classes": [
                    {
                        "name": "Another\\Controller",
                        "usages": [
                            {
                                "file": "/outside/bootstrap.php",
                                "line": 173
                            }
                        ]
                    }
                ],
                "functions": []
            },
            {
                "name": "shadow/package",
                "classes": [
                    {
                        "name": "Forth\\Provider",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 873
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Comparator",
                        "usages": [
                            {
                                "file": "src/Printer.php",
                                "line": 25
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Utils",
                        "usages": [
                            {
                                "file": "src/Utils.php",
                                "line": 19
                            },
                            {
                                "file": "src/Utils.php",
                                "line": 22
                            },
                            {
                                "file": "src/Application.php",
                                "line": 128
                            }
                        ]
                    },
                    {
                        "name": "Third\\Parser",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 317
                            }
                        ]
                    }
                ],
                "functions": [
                    {
                        "name": "shadow_helper",
                        "usages": [
                            {
                                "file": "src/helpers.php",
                                "line": 12
                            }
                        ]
                    }
                ]
            }
        ]
    },
    "devDependenciesInProd": {
        "packages": [
            {
                "name": "some/package",
                "classes": [
                    {
                        "name": "Another\\Command",
                        "usages": [
                            {
                                "file": "src/ProductGenerator.php",
                                "line": 28
                            }
                        ]
                    }
                ],
                "functions": []
            }
        ]
    },
    "prodDependenciesOnlyInDev": {
        "packages": [
            {
                "name": "misplaced/package"
            }
        ]
    },
    "unusedDependencies": {
        "packages": [
            {
                "name": "dead/package"
            }
        ]
    },
    "unusedIgnores": {
        "symbols": [],
        "errors": []
    }
}

OUT;

        self::assertJsonStringEqualsJsonString($this->normalizeEol($expectedVerboseOutput), $verboseOutput);

        $showAllOptions = new CliOptions();
        $showAllOptions->showAllUsages = true;

        $showAllOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($analysisResult, $showAllOptions): void {
            $formatter->format($analysisResult, $showAllOptions, new Configuration());
        });

        $expectedShowAllOutput = <<<'OUT'
{
    "scannedFilesCount": 10,
    "elapsedTime": 0.123,
    "usagesPerSymbolLimit": null,
    "unknownSymbols": {
        "classes": [
            {
                "name": "Unknown\\Thing",
                "usages": [
                    {
                        "file": "app/init.php",
                        "line": 1091
                    },
                    {
                        "file": "app/init.php",
                        "line": 1093
                    }
                ]
            }
        ],
        "functions": [
            {
                "name": "unknown_fn",
                "usages": [
                    {
                        "file": "app/foo.php",
                        "line": 51
                    }
                ]
            }
        ]
    },
    "shadowDependencies": {
        "packages": [
            {
                "name": "shadow/another",
                "classes": [
                    {
                        "name": "Another\\Controller",
                        "usages": [
                            {
                                "file": "/outside/bootstrap.php",
                                "line": 173
                            }
                        ]
                    }
                ],
                "functions": []
            },
            {
                "name": "shadow/package",
                "classes": [
                    {
                        "name": "Forth\\Provider",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 873
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Comparator",
                        "usages": [
                            {
                                "file": "src/Printer.php",
                                "line": 25
                            }
                        ]
                    },
                    {
                        "name": "Shadow\\Utils",
                        "usages": [
                            {
                                "file": "src/Utils.php",
                                "line": 19
                            },
                            {
                                "file": "src/Utils.php",
                                "line": 22
                            },
                            {
                                "file": "src/Application.php",
                                "line": 128
                            },
                            {
                                "file": "src/Controller.php",
                                "line": 229
                            }
                        ]
                    },
                    {
                        "name": "Third\\Parser",
                        "usages": [
                            {
                                "file": "src/bootstrap.php",
                                "line": 317
                            }
                        ]
                    }
                ],
                "functions": [
                    {
                        "name": "shadow_helper",
                        "usages": [
                            {
                                "file": "src/helpers.php",
                                "line": 12
                            }
                        ]
                    }
                ]
            }
        ]
    },
    "devDependenciesInProd": {
        "packages": [
            {
                "name": "some/package",
                "classes": [
                    {
                        "name": "Another\\Command",
                        "usages": [
                            {
                                "file": "src/ProductGenerator.php",
                                "line": 28
                            }
                        ]
                    }
                ],
                "functions": []
            }
        ]
    },
    "prodDependenciesOnlyInDev": {
        "packages": [
            {
                "name": "misplaced/package"
            }
        ]
    },
    "unusedDependencies": {
        "packages": [
            {
                "name": "dead/package"
            }
        ]
    },
    "unusedIgnores": {
        "symbols": [],
        "errors": []
    }
}

OUT;

        self::assertJsonStringEqualsJsonString($this->normalizeEol($expectedShowAllOutput), $showAllOutput);

        $unusedIgnoresResult = new AnalysisResult(
            2,
            0.5,
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [
                new UnusedSymbolIgnore('Foo\\Bar', false, SymbolKind::CLASSLIKE),
                new UnusedSymbolIgnore('~^Legacy\\\\~', true, SymbolKind::CLASSLIKE),
                new UnusedSymbolIgnore('legacy_helper', false, SymbolKind::FUNCTION),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, 'nette/utils'),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, '/app/src/X.php', null),
                new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, '/app/src/X.php', 'nette/utils'),
            ],
        );

        $unusedIgnoresOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($unusedIgnoresResult): void {
            $formatter->format($unusedIgnoresResult, new CliOptions(), new Configuration());
        });

        $expectedUnusedIgnoresOutput = <<<'OUT'
{
    "scannedFilesCount": 2,
    "elapsedTime": 0.5,
    "usagesPerSymbolLimit": 1,
    "unknownSymbols": {
        "classes": [],
        "functions": []
    },
    "shadowDependencies": {
        "packages": []
    },
    "devDependenciesInProd": {
        "packages": []
    },
    "prodDependenciesOnlyInDev": {
        "packages": []
    },
    "unusedDependencies": {
        "packages": []
    },
    "unusedIgnores": {
        "symbols": [
            {
                "kind": "class",
                "name": "Foo\\Bar",
                "regex": false
            },
            {
                "kind": "class",
                "name": "~^Legacy\\\\~",
                "regex": true
            },
            {
                "kind": "function",
                "name": "legacy_helper",
                "regex": false
            }
        ],
        "errors": [
            {
                "errorType": "shadow-dependency",
                "package": null,
                "path": null
            },
            {
                "errorType": "shadow-dependency",
                "package": "nette/utils",
                "path": null
            },
            {
                "errorType": "shadow-dependency",
                "package": null,
                "path": "src/X.php"
            },
            {
                "errorType": "shadow-dependency",
                "package": "nette/utils",
                "path": "src/X.php"
            }
        ]
    }
}

OUT;

        self::assertJsonStringEqualsJsonString($this->normalizeEol($expectedUnusedIgnoresOutput), $unusedIgnoresOutput);
    }

    protected function createFormatter(Printer $printer): ResultFormatter
    {
        return new JsonFormatter('/app', $printer);
    }

}
