<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\ResultFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function str_replace;

class ResultFormatterTest extends TestCase
{

    public function testPrintResult(): void
    {
        // editorconfig-checker-disable
        $formatter = new ResultFormatter('/app', new Printer());

        $noIssuesOutput = $this->captureAndNormalizeOutput(static function () use ($formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], []), new CliOptions(), new Configuration());
        });
        $noIssuesButUnusedIgnores = $this->captureAndNormalizeOutput(static function () use ($formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null)]), new CliOptions(), new Configuration());
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

        $regularOutput = $this->captureAndNormalizeOutput(static function () use ($formatter, $analysisResult): void {
            $formatter->format($analysisResult, new CliOptions(), new Configuration());
        });
        $verboseOutput = $this->captureAndNormalizeOutput(static function () use ($formatter, $analysisResult): void {
            $options = new CliOptions();
            $options->verbose = true;
            $formatter->format($analysisResult, $options, new Configuration());
        });

        $expectedRegularOutput = <<<'OUT'

Unknown classes!
(unable to autoload those, so we cannot check them)

  • Unknown\Thing
    in app/init.php:1093



Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/another
      e.g. Another\Controller in src/bootstrap.php:173

  • shadow/package
      e.g. Forth\Provider in src/bootstrap.php:873 (+ 6 more)



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

  • shadow/another
      Another\Controller
        src/bootstrap.php:173
  • shadow/package
      Forth\Provider
        src/bootstrap.php:873
      Shadow\Comparator
        src/Printer.php:25
      Shadow\Utils
        src/Utils.php:19
        src/Utils.php:22
        src/Application.php:128
        + 1 more
      + 1 more class


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
