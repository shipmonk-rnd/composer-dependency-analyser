<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Result\ResultFormatter;
use function fopen;
use function preg_replace;
use function str_replace;
use function stream_get_contents;

abstract class FormatterTestCase extends TestCase
{

    abstract protected function createFormatter(Printer $printer): ResultFormatter;

    /**
     * @param Closure(ResultFormatter): void $closure
     */
    protected function getFormatterNormalizedOutput(Closure $closure): string
    {
        $stream = fopen('php://memory', 'w');
        self::assertNotFalse($stream);

        $printer = new Printer($stream);
        $formatter = $this->createFormatter($printer);

        $closure($formatter);
        return $this->normalizeEol((string) stream_get_contents($stream, -1, 0));
    }

    protected function normalizeEol(string $string): string
    {
        return str_replace("\r\n", "\n", $string);
    }

    protected function removeColors(string $output): string
    {
        return (string) preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $output);
    }

}
