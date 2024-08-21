<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\ConsoleFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\ResultFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;

class ConsoleFormatterTest extends FormatterTestCase
{

    public function testPrintResult(): void
    {
        // editorconfig-checker-disable
        $noIssuesOutput = $this->getFormatterNormalizedOutput(static function (ResultFormatter $formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [], []), new CliOptions(), new Configuration());
        });
        $noIssuesButUnusedIgnores = $this->getFormatterNormalizedOutput(static function (ResultFormatter $formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [], [new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null)]), new CliOptions(), new Configuration());
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

        self::assertSame($this->normalizeEol($expectedNoIssuesOutput), $noIssuesOutput);
        self::assertSame($this->normalizeEol($expectedNoIssuesButWarningsOutput), $noIssuesButUnusedIgnores);

        $analysisResult = new AnalysisResult(
            10,
            0.123,
            [],
            ['Unknown\\Thing' => [new SymbolUsage('/app/app/init.php', 1093, SymbolKind::CLASSLIKE)]],
            ['Unknown\\function' => [new SymbolUsage('/app/app/foo.php', 51, SymbolKind::FUNCTION)]],
            [
                'shadow/package' => [
                    'Shadow\Utils' => [
                        new SymbolUsage('/app/src/Utils.php', 19, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Utils.php', 22, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Application.php', 128, SymbolKind::CLASSLIKE),
                        new SymbolUsage('/app/src/Controller.php', 229, SymbolKind::CLASSLIKE),
                    ],
                    'Shadow\Comparator' => [new SymbolUsage('/app/src/Printer.php', 25, SymbolKind::CLASSLIKE)],
                    'Third\Parser' => [new SymbolUsage('/app/src/bootstrap.php', 317, SymbolKind::CLASSLIKE)],
                    'Forth\Provider' => [new SymbolUsage('/app/src/bootstrap.php', 873, SymbolKind::CLASSLIKE)],
                ],
                'shadow/another' => [
                    'Another\Controller' => [new SymbolUsage('/app/src/bootstrap.php', 173, SymbolKind::CLASSLIKE)],
                ],
            ],
            ['some/package' => ['Another\Command' => [new SymbolUsage('/app/src/ProductGenerator.php', 28, SymbolKind::CLASSLIKE)]]],
            ['misplaced/package'],
            ['dead/package'],
            []
        );

        $regularOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($analysisResult): void {
            $formatter->format($analysisResult, new CliOptions(), new Configuration());
        });
        $verboseOutput = $this->getFormatterNormalizedOutput(static function ($formatter) use ($analysisResult): void {
            $options = new CliOptions();
            $options->verbose = true;
            $formatter->format($analysisResult, $options, new Configuration());
        });

        $expectedRegularOutput = <<<'OUT'

Found 1 unknown class!
(unable to autoload those, so we cannot check them)

  • Unknown\Thing
    in app/init.php:1093



Found 1 unknown function!
(those are not declared, so we cannot check them)

  • Unknown\function
    in app/foo.php:51



Found 2 shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • shadow/another
      e.g. Another\Controller in src/bootstrap.php:173

  • shadow/package
      e.g. Forth\Provider in src/bootstrap.php:873 (+ 6 more)



Found 1 dev dependency in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
      e.g. Another\Command in src/ProductGenerator.php:28



Found 1 prod dependency used only in dev paths!
(those should probably be moved to "require-dev" section in composer.json)

  • misplaced/package


Found 1 unused dependency!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package

(scanned 10 files in 0.123 s)


OUT;
        $expectedVerboseOutput = <<<'OUT'

Found 1 unknown class!
(unable to autoload those, so we cannot check them)

  • Unknown\Thing
      app/init.php:1093



Found 1 unknown function!
(those are not declared, so we cannot check them)

  • Unknown\function
      app/foo.php:51



Found 2 shadow dependencies!
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
      + 1 more symbol


Found 1 dev dependency in production code!
(those should probably be moved to "require" section in composer.json)

  • some/package
      Another\Command
        src/ProductGenerator.php:28


Found 1 prod dependency used only in dev paths!
(those should probably be moved to "require-dev" section in composer.json)

  • misplaced/package


Found 1 unused dependency!
(those are listed in composer.json, but no usage was found in scanned paths)

  • dead/package

(scanned 10 files in 0.123 s)


OUT;

        self::assertSame($this->normalizeEol($expectedRegularOutput), $regularOutput);
        self::assertSame($this->normalizeEol($expectedVerboseOutput), $verboseOutput);
        // editorconfig-checker-enable
    }

    protected function createFormatter(Printer $printer): ResultFormatter
    {
        return new ConsoleFormatter('/app', $printer);
    }

}
