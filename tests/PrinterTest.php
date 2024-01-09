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

        $noIssuesOutput = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResult(new AnalysisResult([], [], [], []), false);
        });

        self::assertSame("No composer issues found\n\n", $this->removeColors($noIssuesOutput));

        $analysisResult = new AnalysisResult(
            ['Unknown\\Thing' => [new SymbolUsage('app/init.php', 1093)]],
            [
                'shadow/package' => [
                    'Shadow\Utils' => [
                        new SymbolUsage('src/Utils.php', 19),
                        new SymbolUsage('src/Utils.php', 22),
                        new SymbolUsage('src/Application.php', 128),
                        new SymbolUsage('src/Controller.php', 229),
                    ],
                    'Shadow\Comparator' => [new SymbolUsage('src/Printer.php', 25)],
                    'Third\Parser' => [new SymbolUsage('src/bootstrap.php', 317)],
                    'Forth\Provider' => [new SymbolUsage('src/bootstrap.php', 873)],
                ],
                'shadow/another' => [
                    'Another\Controller' => [new SymbolUsage('src/bootstrap.php', 173)],
                ],
            ],
            ['some/package' => ['Another\Command' => [new SymbolUsage('src/ProductGenerator.php', 28)]]],
            ['dead/package']
        );

        $regularOutput = $this->captureAndNormalizeOutput(static function () use ($printer, $analysisResult): void {
            $printer->printResult($analysisResult, false);
        });
        $verboseOutput = $this->captureAndNormalizeOutput(static function () use ($printer, $analysisResult): void {
            $printer->printResult($analysisResult, true);
        });

        // editorconfig-checker-disable
        $expectedRegularOutput = <<<'OUT'

Unknown classes!
(those are not present in composer classmap, so we cannot check them)

  • Unknown\Thing
    in app/init.php:1093



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/package
      e.g. Shadow\Utils in src/Utils.php:19 (+ 6 more)

  • shadow/another
      e.g. Another\Controller in src/bootstrap.php:173



Found dev dependencies in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
      e.g. Another\Command in src/ProductGenerator.php:28



Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package


OUT;
        $expectedVerboseOutput = <<<'OUT'

Unknown classes!
(those are not present in composer classmap, so we cannot check them)

  • Unknown\Thing
      app/init.php:1093



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/package
      Shadow\Utils
        src/Utils.php:19
        src/Utils.php:22
        src/Application.php:128
        + 1 more
      Shadow\Comparator
        src/Printer.php:25
      Third\Parser
        src/bootstrap.php:317
      + 1 more class
  • shadow/another
      Another\Controller
        src/bootstrap.php:173


Found dev dependencies in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
      Another\Command
        src/ProductGenerator.php:28


Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package


OUT;
        // editorconfig-checker-enable
        self::assertSame($this->normalizeEol($expectedRegularOutput), $this->removeColors($regularOutput));
        self::assertSame($this->normalizeEol($expectedVerboseOutput), $this->removeColors($verboseOutput));
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
