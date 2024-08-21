<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use function fopen;
use function stream_get_contents;
use const PHP_EOL;

class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $stream = fopen('php://memory', 'w');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, false);

        $printer->printLine('Hello, <red>world</red>!');
        $printer->print('New line!');

        self::assertSame("Hello, \033[31mworld\033[0m!" . PHP_EOL . 'New line!', stream_get_contents($stream, -1, 0));
    }

    public function testPrintNoColor(): void
    {
        $stream = fopen('php://memory', 'w');
        self::assertNotFalse($stream);

        $printer = new Printer($stream, true);

        $printer->print('Hello, <red>world</red>!');

        self::assertSame('Hello, world!', stream_get_contents($stream, -1, 0));
    }

}
