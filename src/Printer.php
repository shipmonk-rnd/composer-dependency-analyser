<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function array_fill_keys;
use function array_keys;
use function array_reduce;
use function array_values;
use function count;
use function round;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use const PHP_EOL;

class Printer
{

    private const VERBOSE_MAX_EXAMPLE_USAGES = 3;

    private const COLORS = [
        '<red>' => "\033[31m",
        '<green>' => "\033[32m",
        '<orange>' => "\033[33m",
        '<gray>' => "\033[37m",
        '</red>' => "\033[0m",
        '</green>' => "\033[0m",
        '</orange>' => "\033[0m",
        '</gray>' => "\033[0m",
    ];

    /**
     * @var string
     */
    private $cwd;

    public function __construct(string $cwd)
    {
        $this->cwd = $cwd;
    }

    public function printResult(AnalysisResult $result, bool $verbose): int
    {
        if ($result->hasNoErrors()) {
            $elapsed = round($result->getElapsedTime(), 3);
            $this->printLine('');
            $this->printLine('<green>No composer issues found</green>');
            $this->printLine("<gray>(scanned</gray> {$result->getScannedFilesCount()} <gray>files in</gray> {$elapsed} <gray>s)</gray>" . PHP_EOL);
            return 0;
        }

        $classmapErrors = $result->getClassmapErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();

        if (count($classmapErrors) > 0) {
            $this->printClassBasedErrors(
                'Unknown classes!',
                'those are not present in composer classmap, so we cannot check them',
                $classmapErrors,
                $verbose
            );
        }

        if (count($shadowDependencyErrors) > 0) {
            $this->printPackageBasedErrors(
                'Found shadow dependencies!',
                'those are used, but not listed as dependency in composer.json',
                $shadowDependencyErrors,
                $verbose
            );
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $this->printPackageBasedErrors(
                'Found dev dependencies in production code!',
                'those should probably be moved to "require" section in composer.json',
                $devDependencyInProductionErrors,
                $verbose
            );
        }

        if (count($unusedDependencyErrors) > 0) {
            $this->printPackageBasedErrors(
                'Found unused dependencies!',
                'those are listed in composer.json, but no usage was found in scanned paths',
                array_fill_keys($unusedDependencyErrors, []),
                $verbose
            );
        }

        return 255;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     */
    private function printClassBasedErrors(string $title, string $subtitle, array $errors, bool $verbose): void
    {
        $this->printHeader($title, $subtitle);

        foreach ($errors as $classname => $usages) {
            $this->printLine("  • <orange>{$classname}</orange>");

            if ($verbose) {
                foreach ($usages as $index => $usage) {
                    $this->printLine("      <gray>{$this->relativizeUsage($usage)}</gray>");

                    if ($index === self::VERBOSE_MAX_EXAMPLE_USAGES - 1) {
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
    private function printPackageBasedErrors(string $title, string $subtitle, array $errors, bool $verbose): void
    {
        $this->printHeader($title, $subtitle);

        foreach ($errors as $packageName => $usagesPerClassname) {
            $this->printLine("  • <orange>{$packageName}</orange>");

            if (!$verbose) {
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

                        if ($index === self::VERBOSE_MAX_EXAMPLE_USAGES - 1) {
                            $restUsagesCount = count($usages) - $index - 1;

                            if ($restUsagesCount > 0) {
                                $this->printLine("        <gray>+ {$restUsagesCount} more</gray>");
                                break;
                            }
                        }
                    }

                    if ($classnamesPrinted === self::VERBOSE_MAX_EXAMPLE_USAGES) {
                        $restClassnamesCount = count($usagesPerClassname) - $classnamesPrinted;

                        if ($restClassnamesCount > 0) {
                            $this->printLine("      + {$restClassnamesCount} more class" . ($restClassnamesCount > 1 ? 'es' : ''));
                            break;
                        }
                    }
                }
            }
        }

        $this->printLine('');
    }

    private function printHeader(string $title, string $subtitle): void
    {
        $this->printLine('');
        $this->printLine("<red>$title</red>");
        $this->printLine("<gray>($subtitle)</gray>" . PHP_EOL);
    }

    public function printLine(string $string): void
    {
        echo $this->colorize($string) . PHP_EOL;
    }

    private function relativizeUsage(SymbolUsage $usage): string
    {
        $filePath = $usage->getFilepath();

        if (strpos($filePath, $this->cwd) === 0) {
            $relativeFilePath = substr($filePath, strlen($this->cwd) + 1);
        } else {
            $relativeFilePath = $filePath;
        }

        return "{$relativeFilePath}:{$usage->getLineNumber()}";
    }

    private function colorize(string $string): string
    {
        return str_replace(array_keys(self::COLORS), array_values(self::COLORS), $string);
    }

}
