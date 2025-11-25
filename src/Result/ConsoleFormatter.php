<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
use ShipMonk\ComposerDependencyAnalyser\SymbolKind;
use function array_fill_keys;
use function array_reduce;
use function count;
use function fnmatch;
use function in_array;
use function round;
use function strlen;
use function strpos;
use function substr;
use const PHP_EOL;
use const PHP_INT_MAX;

class ConsoleFormatter implements ResultFormatter
{

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var Printer
     */
    private $printer;

    public function __construct(string $cwd, Printer $printer)
    {
        $this->cwd = $cwd;
        $this->printer = $printer;
    }

    public function format(
        AnalysisResult $result,
        CliOptions $options,
        Configuration $configuration
    ): int
    {
        if ($options->dumpUsages !== null) {
            return $this->printResultUsages($result, $options->dumpUsages, $options->showAllUsages === true);
        }

        return $this->printResultErrors($result, $this->getMaxUsagesShownForErrors($options), $configuration->shouldReportUnmatchedIgnoredErrors());
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

    private function printResultUsages(
        AnalysisResult $result,
        string $package,
        bool $showAllUsages
    ): int
    {
        $usagesToDump = $this->filterUsagesToDump($result->getUsages(), $package);
        $maxShownUsages = $showAllUsages ? PHP_INT_MAX : self::VERBOSE_SHOWN_USAGES;
        $totalUsages = $this->countAllUsages($usagesToDump);
        $symbolsWithUsage = $this->countSymbolUsages($usagesToDump);

        $title = $showAllUsages ? "Dumping all usages of $package" : "Dumping sample usages of $package";

        $totalPlural = $totalUsages === 1 ? '' : 's';
        $symbolsPlural = $symbolsWithUsage === 1 ? '' : 's';
        $subtitle = "{$totalUsages} usage{$totalPlural} of {$symbolsWithUsage} symbol{$symbolsPlural} in total";

        $this->printPackageBasedErrors("<orange>$title</orange>", $subtitle, $usagesToDump, $maxShownUsages);

        if ($this->willLimitUsages($usagesToDump, $maxShownUsages)) {
            $this->printLine("<gray>Use --show-all-usages to show all of them</gray>\n");
        }

        return 1;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     * @return array<string, array<string, list<SymbolUsage>>>
     */
    private function filterUsagesToDump(array $usages, string $filter): array
    {
        $result = [];

        foreach ($usages as $package => $usagesPerSymbol) {
            if (fnmatch($filter, $package)) {
                $result[$package] = $usagesPerSymbol;
            }
        }

        return $result;
    }

    private function printResultErrors(
        AnalysisResult $result,
        int $maxShownUsages,
        bool $reportUnmatchedIgnores
    ): int
    {
        $hasError = false;
        $unusedIgnores = $result->getUnusedIgnores();

        $unknownClassErrors = $result->getUnknownClassErrors();
        $unknownFunctionErrors = $result->getUnknownFunctionErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $prodDependencyOnlyInDevErrors = $result->getProdDependencyOnlyInDevErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();

        $unknownClassErrorsCount = count($unknownClassErrors);
        $unknownFunctionErrorsCount = count($unknownFunctionErrors);
        $shadowDependencyErrorsCount = count($shadowDependencyErrors);
        $devDependencyInProductionErrorsCount = count($devDependencyInProductionErrors);
        $prodDependencyOnlyInDevErrorsCount = count($prodDependencyOnlyInDevErrors);
        $unusedDependencyErrorsCount = count($unusedDependencyErrors);

        if ($unknownClassErrorsCount > 0) {
            $hasError = true;
            $classes = $this->pluralize($unknownClassErrorsCount, 'class');
            $this->printSymbolBasedErrors(
                "Found $unknownClassErrorsCount unknown $classes!",
                'unable to autoload those, so we cannot check them',
                $unknownClassErrors,
                $maxShownUsages
            );
        }

        if ($unknownFunctionErrorsCount > 0) {
            $hasError = true;
            $functions = $this->pluralize($unknownFunctionErrorsCount, 'function');
            $this->printSymbolBasedErrors(
                "Found $unknownFunctionErrorsCount unknown $functions!",
                'those are not declared, so we cannot check them',
                $unknownFunctionErrors,
                $maxShownUsages
            );
        }

        if ($shadowDependencyErrorsCount > 0) {
            $hasError = true;
            $dependencies = $this->pluralize($shadowDependencyErrorsCount, 'dependency');
            $this->printPackageBasedErrors(
                "Found $shadowDependencyErrorsCount shadow $dependencies!",
                'those are used, but not listed as dependency in composer.json',
                $shadowDependencyErrors,
                $maxShownUsages
            );
        }

        if ($devDependencyInProductionErrorsCount > 0) {
            $hasError = true;
            $dependencies = $this->pluralize($devDependencyInProductionErrorsCount, 'dependency');
            $this->printPackageBasedErrors(
                "Found $devDependencyInProductionErrorsCount dev $dependencies in production code!",
                'those should probably be moved to "require" section in composer.json',
                $devDependencyInProductionErrors,
                $maxShownUsages
            );
        }

        if ($prodDependencyOnlyInDevErrorsCount > 0) {
            $hasError = true;
            $dependencies = $this->pluralize($prodDependencyOnlyInDevErrorsCount, 'dependency');
            $this->printPackageBasedErrors(
                "Found $prodDependencyOnlyInDevErrorsCount prod $dependencies used only in dev paths!",
                'those should probably be moved to "require-dev" section in composer.json',
                array_fill_keys($prodDependencyOnlyInDevErrors, []),
                $maxShownUsages
            );
        }

        if ($unusedDependencyErrorsCount > 0) {
            $hasError = true;
            $dependencies = $this->pluralize($unusedDependencyErrorsCount, 'dependency');
            $this->printPackageBasedErrors(
                "Found $unusedDependencyErrorsCount unused $dependencies!",
                'those are listed in composer.json, but no usage was found in scanned paths',
                array_fill_keys($unusedDependencyErrors, []),
                $maxShownUsages
            );
        }

        if ($unusedIgnores !== [] && $reportUnmatchedIgnores) {
            $hasError = true;
            $this->printLine('');
            $this->printLine('<orange>Some ignored issues never occurred:</orange>');
            $this->printUnusedIgnores($unusedIgnores);
        }

        if (!$hasError) {
            $this->printLine('');
            $this->printLine('<green>No composer issues found</green>');
        }

        $this->printRunSummary($result);

        return $hasError ? 1 : 0;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     */
    private function printSymbolBasedErrors(string $title, string $subtitle, array $errors, int $maxShownUsages): void
    {
        $this->printHeader($title, $subtitle);

        foreach ($errors as $symbol => $usages) {
            $this->printLine("  • <orange>{$symbol}</orange>");

            if ($maxShownUsages > 1) {
                foreach ($usages as $index => $usage) {
                    $this->printLine("      <gray>{$this->relativizeUsage($usage)}</gray>");

                    if ($index === $maxShownUsages - 1) {
                        $restUsagesCount = count($usages) - $index - 1;

                        if ($restUsagesCount > 0) {
                            $this->printLine("      <gray>+ {$restUsagesCount} more</gray>");
                            break;
                        }
                    }
                }

                $this->printLine('');

            } else {
                $firstUsage = $usages[0];
                $restUsagesCount = count($usages) - 1;
                $rest = $restUsagesCount > 0 ? " (+ {$restUsagesCount} more)" : '';
                $this->printLine("    <gray>in {$this->relativizeUsage($firstUsage)}</gray>$rest" . PHP_EOL);
            }
        }

        $this->printLine('');
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $errors
     */
    private function printPackageBasedErrors(string $title, string $subtitle, array $errors, int $maxShownUsages): void
    {
        $this->printHeader($title, $subtitle);

        foreach ($errors as $packageName => $usagesPerSymbol) {
            $this->printLine("  • <orange>{$packageName}</orange>");

            $this->printUsages($usagesPerSymbol, $maxShownUsages);
        }

        $this->printLine('');
    }

    /**
     * @param array<string, list<SymbolUsage>> $usagesPerSymbol
     */
    private function printUsages(array $usagesPerSymbol, int $maxShownUsages): void
    {
        if ($maxShownUsages === 1) {
            $countOfAllUsages = array_reduce(
                $usagesPerSymbol,
                static function (int $carry, array $usages): int {
                    return $carry + count($usages);
                },
                0
            );

            foreach ($usagesPerSymbol as $symbol => $usages) {
                $firstUsage = $usages[0];
                $restUsagesCount = $countOfAllUsages - 1;
                $rest = $countOfAllUsages > 1 ? " (+ {$restUsagesCount} more)" : '';
                $this->printLine("      <gray>e.g. </gray>{$symbol}<gray> in {$this->relativizeUsage($firstUsage)}</gray>$rest" . PHP_EOL);
                break;
            }
        } else {
            $symbolsPrinted = 0;

            foreach ($usagesPerSymbol as $symbol => $usages) {
                $symbolsPrinted++;
                $this->printLine("      {$symbol}");

                foreach ($usages as $index => $usage) {
                    $this->printLine("       <gray> {$this->relativizeUsage($usage)}</gray>");

                    if ($index === $maxShownUsages - 1) {
                        $restUsagesCount = count($usages) - $index - 1;

                        if ($restUsagesCount > 0) {
                            $this->printLine("        <gray>+ {$restUsagesCount} more</gray>");
                            break;
                        }
                    }
                }

                if ($symbolsPrinted === $maxShownUsages) {
                    $restSymbolsCount = count($usagesPerSymbol) - $symbolsPrinted;

                    if ($restSymbolsCount > 0) {
                        $this->printLine("      + {$restSymbolsCount} more symbol" . ($restSymbolsCount > 1 ? 's' : ''));
                        break;
                    }
                }
            }
        }
    }

    private function printHeader(string $title, string $subtitle): void
    {
        $this->printLine('');
        $this->printLine("<red>$title</red>");
        $this->printLine("<gray>($subtitle)</gray>" . PHP_EOL);
    }

    private function printLine(string $string): void
    {
        $this->printer->printLine($string);
    }

    private function relativizeUsage(SymbolUsage $usage): string
    {
        return "{$this->relativizePath($usage->getFilepath())}:{$usage->getLineNumber()}";
    }

    private function relativizePath(string $path): string
    {
        if (strpos($path, $this->cwd) === 0) {
            return substr($path, strlen($this->cwd) + 1);
        }

        return $path;
    }

    /**
     * @param list<UnusedSymbolIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    private function printUnusedIgnores(array $unusedIgnores): void
    {
        foreach ($unusedIgnores as $unusedIgnore) {
            if ($unusedIgnore instanceof UnusedSymbolIgnore) {
                $this->printSymbolBasedUnusedIgnore($unusedIgnore);
            } else {
                $this->printErrorBasedUnusedIgnore($unusedIgnore);
            }
        }

        $this->printLine('');
    }

    private function printSymbolBasedUnusedIgnore(UnusedSymbolIgnore $unusedIgnore): void
    {
        $kind = $unusedIgnore->getSymbolKind() === SymbolKind::CLASSLIKE ? 'class' : 'function';
        $regex = $unusedIgnore->isRegex() ? ' regex' : '';
        $this->printLine(" • <gray>Unknown {$kind}{$regex}</gray> '{$unusedIgnore->getUnknownSymbol()}' <gray>was ignored, but it was never applied.</gray>");
    }

    private function printErrorBasedUnusedIgnore(UnusedErrorIgnore $unusedIgnore): void
    {
        $package = $unusedIgnore->getPackage();
        $path = $unusedIgnore->getPath();

        if ($package === null && $path === null) {
            $this->printLine(" • <gray>Error</gray> '{$unusedIgnore->getErrorType()}' <gray>was globally ignored, but it was never applied.</gray>");
        }

        if ($package !== null && $path === null) {
            $this->printLine(" • <gray>Error</gray> '{$unusedIgnore->getErrorType()}' <gray>was ignored for package</gray> '{$package}', <gray>but it was never applied.</gray>");
        }

        if ($package === null && $path !== null) {
            $this->printLine(" • <gray>Error</gray> '{$unusedIgnore->getErrorType()}' <gray>was ignored for path</gray> '{$this->relativizePath($path)}', <gray>but it was never applied.</gray>");
        }

        if ($package !== null && $path !== null) {
            $this->printLine(" • <gray>Error</gray> '{$unusedIgnore->getErrorType()}' <gray>was ignored for package</gray> '{$package}' <gray> and path</gray> '{$this->relativizePath($path)}', <gray>but it was never applied.</gray>");
        }
    }

    private function printRunSummary(AnalysisResult $result): void
    {
        $elapsed = round($result->getElapsedTime(), 3);
        $this->printLine("<gray>(scanned</gray> {$result->getScannedFilesCount()} <gray>files in</gray> {$elapsed} <gray>s)</gray>" . PHP_EOL);
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     */
    private function countAllUsages(array $usages): int
    {
        $total = 0;

        foreach ($usages as $usagesPerSymbol) {
            foreach ($usagesPerSymbol as $symbolUsages) {
                $total += count($symbolUsages);
            }
        }

        return $total;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     */
    private function countSymbolUsages(array $usages): int
    {
        $total = 0;

        foreach ($usages as $usagesPerSymbol) {
            $total += count($usagesPerSymbol);
        }

        return $total;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     */
    private function willLimitUsages(array $usages, int $limit): bool
    {
        foreach ($usages as $usagesPerSymbol) {
            if (count($usagesPerSymbol) > $limit) {
                return true;
            }

            foreach ($usagesPerSymbol as $symbolUsages) {
                if (count($symbolUsages) > $limit) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pluralize(int $count, string $singular): string
    {
        if ($count === 1) {
            return $singular;
        }

        if (substr($singular, -1) === 's' || substr($singular, -1) === 'x' || substr($singular, -2) === 'sh' || substr($singular, -2) === 'ch') {
            return $singular . 'es';
        }

        if (substr($singular, -1) === 'y' && !in_array($singular[strlen($singular) - 2], ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($singular, 0, -1) . 'ies';
        }

        return $singular . 's';
    }

}
