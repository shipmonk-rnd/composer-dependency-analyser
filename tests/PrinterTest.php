<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\Composer\Result\AnalysisResult;
use ShipMonk\Composer\Result\SymbolUsage;
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
            $printer->printResult(new AnalysisResult([], [], [], []), false);
        });

        self::assertSame("No composer issues found\n\n", $this->removeColors($output1));

        $output2 = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResult(
                new AnalysisResult(
                    ['Unknown\\Thing' => [new SymbolUsage('app/init.php', 1093)]],
                    [
                        'shadow/package' => [
                            'Shadow\Utils' => [
                                new SymbolUsage('src/Utils.php', 19),
                                new SymbolUsage('src/Utils.php', 22),
                            ],
                            'Shadow\Comparator' => [new SymbolUsage('src/bootstrap.php', 25)],
                        ],
                        'shadow/another' => [
                            'Another\Controller' => [new SymbolUsage('src/Printer.php', 173)],
                        ],
                    ],
                    ['some/package' => ['Another\Command' => [new SymbolUsage('src/ProductGenerator.php', 28)]]],
                    ['dead/package']
                ),
                false
            );
        });

        // editorconfig-checker-disable
        $fullOutput = <<<'OUT'

Unknown classes!
(those are not present in composer classmap, so we cannot check them)

  • Unknown\Thing
    in app/init.php:1093



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/package
      Shadow\Utils in src/Utils.php:19 (+ 2 more)

  • shadow/another
      Another\Controller in src/Printer.php:173



Found dev dependencies in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
      Another\Command in src/ProductGenerator.php:28



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
