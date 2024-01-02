<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\SymbolError;
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
        bool $verbose
    ): int
    {
        if (count($errors) === 0) {
            $this->printLine('<green>No composer issues found</green>' . PHP_EOL);
            return 0;
        }

        $classmapErrors = $this->filterErrors($errors, ClassmapEntryMissingError::class);
        $shadowDependencyErrors = $this->filterErrors($errors, ShadowDependencyError::class);
        $devDependencyInProductionErrors = $this->filterErrors($errors, DevDependencyInProductionCodeError::class);

        if (count($classmapErrors) > 0) {
            $this->printErrors(
                'Classes not found in composer classmap!',
                'this usually means that preconditions are not met, see readme',
                $classmapErrors,
                $verbose,
            );
        }

        if (count($shadowDependencyErrors) > 0) {
            $this->printErrors(
                'Found shadow dependencies!',
                'those are used, but not listed as dependency in composer.json',
                $shadowDependencyErrors,
                $verbose,
            );
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $this->printErrors(
                'Found dev dependencies in production code!',
                '(those are wrongly listed as dev dependency in composer.json)',
                $devDependencyInProductionErrors,
                $verbose,
            );
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
            $append = $error->getPackageName() !== null ? " ({$error->getPackageName()})" : '';

            $this->printLine("  • <orange>{$error->getSymbolName()}</orange>$append");

            if ($verbose) {
                $this->printLine("    <gray>first usage in {$error->getExampleUsageFilepath()}</gray>" . PHP_EOL);
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