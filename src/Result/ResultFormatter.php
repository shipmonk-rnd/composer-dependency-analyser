<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedClassIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
use function array_fill_keys;
use function array_reduce;
use function count;
use function fnmatch;
use function round;
use function strlen;
use function strpos;
use function substr;
use const PHP_EOL;
use const PHP_INT_MAX;

class ResultFormatter
{

    public const VERBOSE_SHOWN_USAGES = 3;

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
        if ($options->verbose === true) {
            return self::VERBOSE_SHOWN_USAGES;
        }

        if ($options->showAllUsages === true) {
            return PHP_INT_MAX;
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
        $classesWithUsage = $this->countClassUsages($usagesToDump);

        $title = $showAllUsages ? "Dumping all usages of $package" : "Dumping sample usages of $package";

        $totalPlural = $totalUsages === 1 ? '' : 's';
        $classesPlural = $classesWithUsage === 1 ? '' : 'es';
        $subtitle = "{$totalUsages} usage{$totalPlural} of {$classesWithUsage} class{$classesPlural} in total";

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

        foreach ($usages as $package => $usagesPerClassname) {
            if (fnmatch($filter, $package)) {
                $result[$package] = $usagesPerClassname;
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

        $classmapErrors = $result->getClassmapErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $prodDependencyOnlyInDevErrors = $result->getProdDependencyOnlyInDevErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();

        if (count($classmapErrors) > 0) {
            $hasError = true;
            $this->printClassBasedErrors(
                'Unknown classes!',
                'unable to autoload those, so we cannot check them',
                $classmapErrors,
                $maxShownUsages
            );
        }

        if (count($shadowDependencyErrors) > 0) {
            $hasError = true;
            $this->printPackageBasedErrors(
                'Found shadow dependencies!',
                'those are used, but not listed as dependency in composer.json',
                $shadowDependencyErrors,
                $maxShownUsages
            );
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $hasError = true;
            $this->printPackageBasedErrors(
                'Found dev dependencies in production code!',
                'those should probably be moved to "require" section in composer.json',
                $devDependencyInProductionErrors,
                $maxShownUsages
            );
        }

        if (count($prodDependencyOnlyInDevErrors) > 0) {
            $hasError = true;
            $this->printPackageBasedErrors(
                'Found prod dependencies used only in dev paths!',
                'those should probably be moved to "require-dev" section in composer.json',
                array_fill_keys($prodDependencyOnlyInDevErrors, []),
                $maxShownUsages
            );
        }

        if (count($unusedDependencyErrors) > 0) {
            $hasError = true;
            $this->printPackageBasedErrors(
                'Found unused dependencies!',
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

        return $hasError ? 255 : 0;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     */
    private function printClassBasedErrors(string $title, string $subtitle, array $errors, int $maxShownUsages): void
    {
        $this->printHeader($title, $subtitle);

        foreach ($errors as $classname => $usages) {
            $this->printLine("  • <orange>{$classname}</orange>");

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

        foreach ($errors as $packageName => $usagesPerClassname) {
            $this->printLine("  • <orange>{$packageName}</orange>");

            $this->printUsages($usagesPerClassname, $maxShownUsages);
        }

        $this->printLine('');
    }

    /**
     * @param array<string, list<SymbolUsage>> $usagesPerClassname
     */
    private function printUsages(array $usagesPerClassname, int $maxShownUsages): void
    {
        if ($maxShownUsages === 1) {
            $countOfAllUsages = array_reduce(
                $usagesPerClassname,
                static function (int $carry, array $usages): int {
                    return $carry + count($usages);
                },
                0
            );

            foreach ($usagesPerClassname as $classname => $usages) {
                $firstUsage = $usages[0];
                $restUsagesCount = $countOfAllUsages - 1;
                $rest = $countOfAllUsages > 1 ? " (+ {$restUsagesCount} more)" : '';
                $this->printLine("      <gray>e.g. </gray>{$classname}<gray> in {$this->relativizeUsage($firstUsage)}</gray>$rest" . PHP_EOL);
                break;
            }
        } else {
            $classnamesPrinted = 0;

            foreach ($usagesPerClassname as $classname => $usages) {
                $classnamesPrinted++;
                $this->printLine("      {$classname}");

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

                if ($classnamesPrinted === $maxShownUsages) {
                    $restClassnamesCount = count($usagesPerClassname) - $classnamesPrinted;

                    if ($restClassnamesCount > 0) {
                        $this->printLine("      + {$restClassnamesCount} more class" . ($restClassnamesCount > 1 ? 'es' : ''));
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

    public function printLine(string $string): void
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
            return (string) substr($path, strlen($this->cwd) + 1);
        }

        return $path;
    }

    /**
     * @param list<UnusedClassIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    private function printUnusedIgnores(array $unusedIgnores): void
    {
        foreach ($unusedIgnores as $unusedIgnore) {
            if ($unusedIgnore instanceof UnusedClassIgnore) {
                $this->printClassBasedUnusedIgnore($unusedIgnore);
            } else {
                $this->printErrorBasedUnusedIgnore($unusedIgnore);
            }
        }

        $this->printLine('');
    }

    private function printClassBasedUnusedIgnore(UnusedClassIgnore $unusedIgnore): void
    {
        $regex = $unusedIgnore->isRegex() ? ' regex' : '';
        $this->printLine(" • <gray>Unknown class{$regex}</gray> '{$unusedIgnore->getUnknownClass()}' <gray>was ignored, but it was never applied.</gray>");
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

        foreach ($usages as $usagesPerClassname) {
            foreach ($usagesPerClassname as $classUsages) {
                $total += count($classUsages);
            }
        }

        return $total;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     */
    private function countClassUsages(array $usages): int
    {
        $total = 0;

        foreach ($usages as $usagesPerClassname) {
            $total += count($usagesPerClassname);
        }

        return $total;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages
     */
    private function willLimitUsages(array $usages, int $limit): bool
    {
        foreach ($usages as $usagesPerClassname) {
            if (count($usagesPerClassname) > $limit) {
                return true;
            }

            foreach ($usagesPerClassname as $classUsages) {
                if (count($classUsages) > $limit) {
                    return true;
                }
            }
        }

        return false;
    }

}
