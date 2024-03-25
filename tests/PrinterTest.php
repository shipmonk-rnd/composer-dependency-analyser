<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use const PHP_EOL;

class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $printer = new Printer();

        $this->expectOutputString("Hello, \033[31mworld\033[0m!" . PHP_EOL);
        $printer->printLine('Hello, <red>world</red>!');
    }

    public function testPrint(): void
    {
        $printer = new Printer();

        $this->expectOutputString("Hello, \033[31mworld\033[0m!");
        $printer->print('Hello, <red>world</red>!');
    }

}
