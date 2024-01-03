<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function str_replace;

class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $printer = new Printer();

        $output = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printLine('Hello, <red>world</red>!');
        });

        self::assertSame("Hello, \033[31mworld\033[0m!\n", $output);
        self::assertSame("Hello, world!\n", $this->removeColors($output));
    }

    public function testPrintResult(): void
    {
        $printer = new Printer();

        $output1 = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResult([], false, false);
        });

        self::assertSame("No composer issues found\n\n", $this->removeColors($output1));

        $output2 = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResult([
                new ClassmapEntryMissingError('Foo', 'foo.php'),
                new ShadowDependencyError('Bar', 'some/package', 'bar.php'),
                new DevDependencyInProductionCodeError('Baz', 'some/package', 'baz.php'),
            ], false, true);
        });

        // editorconfig-checker-disable
        $fullOutput = <<<'OUT'

Unknown classes!
(those are not present in composer classmap, so we cannot check them)

  • Foo
    first usage in foo.php



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • Bar (some/package)
    first usage in bar.php



Found dev dependencies in production code!
(those are wrongly listed as dev dependency in composer.json)

  • Baz (some/package)
    first usage in baz.php



OUT;
        // editorconfig-checker-enable
        self::assertSame($this->normalizeEol($fullOutput), $this->removeColors($output2));
    }

    private function removeColors(string $output): string
    {
        return (string) preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $output);
    }

    /**
     * @param Closure(): void $closure
     */
    private function captureAndNormalizeOutput(Closure $closure): string
    {
        ob_start();
        $closure();
        return $this->normalizeEol((string) ob_get_clean());
    }

    private function normalizeEol(string $string): string
    {
        return str_replace("\r\n", "\n", $string);
    }

}
