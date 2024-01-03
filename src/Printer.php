<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use LogicException;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\SymbolError;
use ShipMonk\Composer\Error\UnusedDependencyError;
use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function is_a;
use function str_replace;
use const PHP_EOL;

class Printer
{

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
     * @param list<SymbolError> $errors
     */
    public function printResult(
        array $errors,
        bool $ignoreUnknownClasses,
        bool $verbose
    ): int
    {
        $errorReported = false;
        $classmapErrors = $this->filterErrors($errors, ClassmapEntryMissingError::class);
        $shadowDependencyErrors = $this->filterErrors($errors, ShadowDependencyError::class);
        $devDependencyInProductionErrors = $this->filterErrors($errors, DevDependencyInProductionCodeError::class);
        $unusedDependencyErrors = $this->filterErrors($errors, UnusedDependencyError::class);

        if (count($classmapErrors) > 0 && !$ignoreUnknownClasses) {
            $this->printErrors(
                'Unknown classes!',
                'those are not present in composer classmap, so we cannot check them',
                $classmapErrors,
                $verbose
            );
            $errorReported = true;
        }

        if (count($shadowDependencyErrors) > 0) {
            $this->printErrors(
                'Found shadow dependencies!',
                'those are used, but not listed as dependency in composer.json',
                $shadowDependencyErrors,
                $verbose
            );
            $errorReported = true;
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $this->printErrors(
                'Found dev dependencies in production code!',
                'those are wrongly listed as dev dependency in composer.json',
                $devDependencyInProductionErrors,
                $verbose
            );
            $errorReported = true;
        }

        if (count($unusedDependencyErrors) > 0) {
            $this->printErrors(
                'Found unused dependencies!',
                'those are listed in composer.json, but not used',
                $unusedDependencyErrors,
                $verbose
            );
            $errorReported = true;
        }

        if (!$errorReported) {
            $this->printLine('<green>No composer issues found</green>' . PHP_EOL);
            return 0;
        }

        return 255;
    }

    /**
     * @param list<SymbolError> $errors
     */
    private function printErrors(string $title, string $subtitle, array $errors, bool $verbose): void
    {
        $this->printLine('');
        $this->printLine("<red>$title</red>");
        $this->printLine("<gray>($subtitle)</gray>" . PHP_EOL);

        foreach ($errors as $error) {
            $usage = $error->getExampleUsage();

            if ($error->getPackageName() !== null) {
                $this->printLine("  • <orange>{$error->getPackageName()}</orange>");

                if ($usage !== null) {
                    $this->printLine("    <gray>e.g. {$usage->getClassname()} in {$usage->getFilepath()}:{$usage->getLineNumber()}</gray>" . PHP_EOL);
                }
            } else {
                if ($usage === null) {
                    throw new LogicException('Either packageName or exampleUsage must be set');
                }

                $this->printLine("  • <orange>{$usage->getClassname()}</orange>");
                $this->printLine("    <gray>e.g. in {$usage->getFilepath()}:{$usage->getLineNumber()}</gray>" . PHP_EOL);
            }
        }

        $this->printLine('');
    }

    public function printLine(string $string): void
    {
        echo $this->colorize($string) . PHP_EOL;
    }

    private function colorize(string $string): string
    {
        return str_replace(array_keys(self::COLORS), array_values(self::COLORS), $string);
    }

    /**
     * @template T of SymbolError
     * @param list<SymbolError> $errors
     * @param class-string<T> $class
     * @return list<T>
     */
    private function filterErrors(array $errors, string $class): array
    {
        $filtered = array_filter($errors, static function (SymbolError $error) use ($class): bool {
            return is_a($error, $class, true);
        });
        return array_values($filtered);
    }

}
