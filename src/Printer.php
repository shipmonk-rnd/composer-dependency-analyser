<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use function array_keys;
use function array_values;
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

    public function printLine(string $string): void
    {
        echo $this->colorize($string) . PHP_EOL;
    }

    public function print(string $string): void
    {
        echo $this->colorize($string);
    }

    private function colorize(string $string): string
    {
        return str_replace(array_keys(self::COLORS), array_values(self::COLORS), $string);
    }

}
