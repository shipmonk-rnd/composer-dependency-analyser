<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Crate\ClassUsage;
use ShipMonk\Composer\Error\ClassmapEntryMissingError;
use ShipMonk\Composer\Error\DevDependencyInProductionCodeError;
use ShipMonk\Composer\Error\ShadowDependencyError;
use ShipMonk\Composer\Error\UnusedDependencyError;
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
            $printer->printResult([]);
        });

        self::assertSame("No composer issues found\n\n", $this->removeColors($output1));

        $output2 = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResult([
                new ClassmapEntryMissingError(new ClassUsage('Foo', 'foo.php', 11)),
                new ShadowDependencyError('shadow/package', new ClassUsage('Bar', 'bar.php', 22)),
                new DevDependencyInProductionCodeError('some/package', new ClassUsage('Baz', 'baz.php', 33)),
                new UnusedDependencyError('dead/package'),
            ]);
        });

        // editorconfig-checker-disable
        $fullOutput = <<<'OUT'

Unknown classes!
(those are not present in composer classmap, so we cannot check them)

  • Foo
    e.g. in foo.php:11



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/package
    e.g. Bar in bar.php:22



Found dev dependencies in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
    e.g. Baz in baz.php:33



Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package


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
