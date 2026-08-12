<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
use ShipMonk\ComposerDependencyAnalyser\SymbolKind;
use function array_map;
use function array_slice;
use function json_encode;
use function str_starts_with;
use function strlen;
use function substr;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_INT_MAX;

class JsonFormatter implements ResultFormatter
{

    public function __construct(
        private string $cwd,
        private Printer $printer,
    )
    {
    }

    public function format(
        AnalysisResult $result,
        CliOptions $options,
        Configuration $configuration,
    ): int
    {
        $hasError = false;

        $unknownClassErrors = $result->getUnknownClassErrors();
        $unknownFunctionErrors = $result->getUnknownFunctionErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $prodDependencyOnlyInDevErrors = $result->getProdDependencyOnlyInDevErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();
        $unusedIgnores = $result->getUnusedIgnores();

        if (
            $unknownClassErrors !== []
            || $unknownFunctionErrors !== []
            || $shadowDependencyErrors !== []
            || $devDependencyInProductionErrors !== []
            || $prodDependencyOnlyInDevErrors !== []
            || $unusedDependencyErrors !== []
        ) {
            $hasError = true;
        }

        if ($unusedIgnores !== [] && $configuration->shouldReportUnmatchedIgnoredErrors()) {
            $hasError = true;
        }

        $usagesLimit = $this->getMaxUsagesShownForErrors($options);

        $data = [
            'scannedFilesCount' => $result->getScannedFilesCount(),
            'elapsedTime' => $result->getElapsedTime(),
            'usagesPerSymbolLimit' => $usagesLimit === PHP_INT_MAX ? null : $usagesLimit,
            'unknownSymbols' => [
                'classes' => $this->serializeSymbolErrors($unknownClassErrors, $usagesLimit),
                'functions' => $this->serializeSymbolErrors($unknownFunctionErrors, $usagesLimit),
            ],
            'shadowDependencies' => [
                'packages' => $this->serializePackageErrors($shadowDependencyErrors, $usagesLimit),
            ],
            'devDependenciesInProd' => [
                'packages' => $this->serializePackageErrors($devDependencyInProductionErrors, $usagesLimit),
            ],
            'prodDependenciesOnlyInDev' => [
                'packages' => $this->serializePackageList($prodDependencyOnlyInDevErrors),
            ],
            'unusedDependencies' => [
                'packages' => $this->serializePackageList($unusedDependencyErrors),
            ],
            'unusedIgnores' => $this->serializeUnusedIgnores($unusedIgnores),
        ];

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $this->printer->print($json . "\n");

        if ($hasError) {
            return 1;
        }

        return 0;
    }

    private function getMaxUsagesShownForErrors(CliOptions $options): int
    {
        if ($options->showAllUsages === true) {
            return PHP_INT_MAX;
        }

        if ($options->verbose === true) {
            return self::VERBOSE_SHOWN_USAGES;
        }

        return 1;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     * @return list<array{name: string, usages: list<array{file: string, line: int}>}>
     */
    private function serializeSymbolErrors(
        array $errors,
        int $usagesLimit,
    ): array
    {
        $result = [];

        foreach ($errors as $symbol => $usages) {
            $result[] = [
                'name' => $symbol,
                'usages' => $this->serializeUsages($usages, $usagesLimit),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $errors
     * @return list<array{name: string, classes: list<array{name: string, usages: list<array{file: string, line: int}>}>, functions: list<array{name: string, usages: list<array{file: string, line: int}>}>}>
     */
    private function serializePackageErrors(
        array $errors,
        int $usagesLimit,
    ): array
    {
        $result = [];

        foreach ($errors as $package => $usagesPerSymbol) {
            $classes = [];
            $functions = [];

            foreach ($usagesPerSymbol as $symbol => $usages) {
                $serializedSymbol = [
                    'name' => $symbol,
                    'usages' => $this->serializeUsages($usages, $usagesLimit),
                ];

                $firstUsage = $usages[0] ?? null;

                if ($firstUsage !== null && $firstUsage->getKind() === SymbolKind::FUNCTION) {
                    $functions[] = $serializedSymbol;
                } else {
                    $classes[] = $serializedSymbol;
                }
            }

            $result[] = [
                'name' => $package,
                'classes' => $classes,
                'functions' => $functions,
            ];
        }

        return $result;
    }

    /**
     * @param list<string> $packages
     * @return list<array{name: string}>
     */
    private function serializePackageList(array $packages): array
    {
        return array_map(static function (string $name): array {
            return ['name' => $name];
        }, $packages);
    }

    /**
     * @param list<SymbolUsage> $usages
     * @return list<array{file: string, line: int}>
     */
    private function serializeUsages(
        array $usages,
        int $usagesLimit,
    ): array
    {
        return array_map(function (SymbolUsage $usage): array {
            return [
                'file' => $this->relativizePath($usage->getFilepath()),
                'line' => $usage->getLineNumber(),
            ];
        }, array_slice($usages, 0, $usagesLimit));
    }

    /**
     * @param list<UnusedSymbolIgnore|UnusedErrorIgnore> $unusedIgnores
     * @return array{symbols: list<array{kind: string, name: string, regex: bool}>, errors: list<array{errorType: string, package: string|null, path: string|null}>}
     */
    private function serializeUnusedIgnores(array $unusedIgnores): array
    {
        $symbols = [];
        $errors = [];

        foreach ($unusedIgnores as $ignore) {
            if ($ignore instanceof UnusedSymbolIgnore) {
                $symbols[] = [
                    'kind' => $ignore->getSymbolKind() === SymbolKind::CLASSLIKE ? 'class' : 'function',
                    'name' => $ignore->getUnknownSymbol(),
                    'regex' => $ignore->isRegex(),
                ];

                continue;
            }

            $path = $ignore->getPath();

            $errors[] = [
                'errorType' => $ignore->getErrorType(),
                'package' => $ignore->getPackage(),
                'path' => $path === null ? null : $this->relativizePath($path),
            ];
        }

        return [
            'symbols' => $symbols,
            'errors' => $errors,
        ];
    }

    private function relativizePath(string $path): string
    {
        if (str_starts_with($path, $this->cwd)) {
            return substr($path, strlen($this->cwd) + 1);
        }

        return $path;
    }

}
