<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function str_replace;

class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $printer = new Printer('');

        $output = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printLine('Hello, <red>world</red>!');
        });

        self::assertSame("Hello, \033[31mworld\033[0m!\n", $output);
        self::assertSame("Hello, world!\n", $this->removeColors($output));
    }

    public function testPrintResult(): void
    {
        // editorconfig-checker-disable
        $printer = new Printer('/app');

        $noIssuesOutput = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResultErrors(new AnalysisResult(2, 0.123, [], [], [], [], [], [], []), 1, true);
        });
        $noIssuesButUnusedIgnores = $this->captureAndNormalizeOutput(static function () use ($printer): void {
            $printer->printResultErrors(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null)]), 3, true);
        });

        $expectedNoIssuesOutput = <<<'OUT'

No composer issues found
(scanned 2 files in 0.123 s)


OUT;

        $expectedNoIssuesButWarningsOutput = <<<'OUT'

Some ignored issues never occurred:
 • Error 'shadow-dependency' was globally ignored, but it was never applied.

(scanned 2 files in 0.123 s)


OUT;

        self::assertSame($this->normalizeEol($expectedNoIssuesOutput), $this->removeColors($noIssuesOutput));
        self::assertSame($this->normalizeEol($expectedNoIssuesButWarningsOutput), $this->removeColors($noIssuesButUnusedIgnores));

        $analysisResult = new AnalysisResult(
            10,
            0.123,
            [],
            ['Unknown\\Thing' => [new SymbolUsage('/app/app/init.php', 1093)]],
            [
                'shadow/package' => [
                    'Shadow\Utils' => [
                        new SymbolUsage('/app/src/Utils.php', 19),
                        new SymbolUsage('/app/src/Utils.php', 22),
                        new SymbolUsage('/app/src/Application.php', 128),
                        new SymbolUsage('/app/src/Controller.php', 229),
                    ],
                    'Shadow\Comparator' => [new SymbolUsage('/app/src/Printer.php', 25)],
                    'Third\Parser' => [new SymbolUsage('/app/src/bootstrap.php', 317)],
                    'Forth\Provider' => [new SymbolUsage('/app/src/bootstrap.php', 873)],
                ],
                'shadow/another' => [
                    'Another\Controller' => [new SymbolUsage('/app/src/bootstrap.php', 173)],
                ],
            ],
            ['some/package' => ['Another\Command' => [new SymbolUsage('/app/src/ProductGenerator.php', 28)]]],
            ['misplaced/package'],
            ['dead/package'],
            []
        );

        $regularOutput = $this->captureAndNormalizeOutput(static function () use ($printer, $analysisResult): void {
            $printer->printResultErrors($analysisResult, 1, true);
        });
        $verboseOutput = $this->captureAndNormalizeOutput(static function () use ($printer, $analysisResult): void {
            $printer->printResultErrors($analysisResult, 3, true);
        });

        $expectedRegularOutput = <<<'OUT'

Unknown classes!
(unable to autoload those, so we cannot check them)

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



Found prod dependencies used only in dev paths!
(those should probably be moved to "require-dev" section in composer.json)

  • misplaced/package


Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package

(scanned 10 files in 0.123 s)


OUT;
        $expectedVerboseOutput = <<<'OUT'

Unknown classes!
(unable to autoload those, so we cannot check them)

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


Found prod dependencies used only in dev paths!
(those should probably be moved to "require-dev" section in composer.json)

  • misplaced/package


Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package

(scanned 10 files in 0.123 s)


OUT;

        self::assertSame($this->normalizeEol($expectedRegularOutput), $this->removeColors($regularOutput));
        self::assertSame($this->normalizeEol($expectedVerboseOutput), $this->removeColors($verboseOutput));
        // editorconfig-checker-enable
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
