<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;

class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $printer = new Printer();

        $this->expectOutputString("Hello, \033[31mworld\033[0m!\n");
        $printer->printLine('Hello, <red>world</red>!');
    }

}
