<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\JunitFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\ResultFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;

class JunitFormatterTest extends FormatterTestCase
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
<?xml version="1.0" encoding="UTF-8"?>
<testsuites></testsuites>
OUT;

        $expectedNoIssuesButWarningsOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="unused-ignore" failures="1">
    <testcase name="shadow-dependency">
      <failure>'shadow-dependency' was globally ignored, but it was never applied.</failure>
    </testcase>
  </testsuite>
  <!-- showing only first example failure usage -->
</testsuites>
OUT;

        self::assertSame($this->normalizeEol($expectedNoIssuesOutput), $noIssuesOutput);
        self::assertSame($this->normalizeEol($expectedNoIssuesButWarningsOutput), $noIssuesButUnusedIgnores);

        $analysisResult = new AnalysisResult(
            10,
            0.123,
            [],
            ['Unknown\\Thing' => [
                new SymbolUsage('/app/app/init.php', 1091, SymbolKind::CLASSLIKE),
                new SymbolUsage('/app/app/init.php', 1093, SymbolKind::CLASSLIKE),
            ]],
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
            $options->showAllUsages = true;
            $formatter->format($analysisResult, $options, new Configuration());
        });

        $expectedRegularOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="unknown classes" failures="1">
    <testcase name="Unknown\Thing">
      <failure>app/init.php:1091</failure>
    </testcase>
  </testsuite>
  <testsuite name="unknown functions" failures="1">
    <testcase name="Unknown\function">
      <failure>app/foo.php:51</failure>
    </testcase>
  </testsuite>
  <testsuite name="shadow dependencies" failures="2">
    <testcase name="shadow/another">
      <failure message="Another\Controller">src/bootstrap.php:173</failure>
    </testcase>
    <testcase name="shadow/package">
      <failure message="Forth\Provider">src/bootstrap.php:873</failure>
    </testcase>
  </testsuite>
  <testsuite name="dev dependencies in production code" failures="1">
    <testcase name="some/package">
      <failure message="Another\Command">src/ProductGenerator.php:28</failure>
    </testcase>
  </testsuite>
  <testsuite name="prod dependencies used only in dev paths" failures="1">
    <testcase name="misplaced/package"></testcase>
  </testsuite>
  <testsuite name="unused dependencies" failures="1">
    <testcase name="dead/package"></testcase>
  </testsuite>
  <!-- showing only first example failure usage -->
</testsuites>
OUT;
        $expectedVerboseOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="unknown classes" failures="1">
    <testcase name="Unknown\Thing">
      <failure>app/init.php:1091</failure>
      <failure>app/init.php:1093</failure>
    </testcase>
  </testsuite>
  <testsuite name="unknown functions" failures="1">
    <testcase name="Unknown\function">
      <failure>app/foo.php:51</failure>
    </testcase>
  </testsuite>
  <testsuite name="shadow dependencies" failures="2">
    <testcase name="shadow/another">
      <failure message="Another\Controller">src/bootstrap.php:173</failure>
    </testcase>
    <testcase name="shadow/package">
      <failure message="Forth\Provider">src/bootstrap.php:873</failure>
      <failure message="Shadow\Comparator">src/Printer.php:25</failure>
      <failure message="Shadow\Utils">src/Utils.php:19</failure>
      <failure message="Shadow\Utils">src/Utils.php:22</failure>
      <failure message="Shadow\Utils">src/Application.php:128</failure>
      <failure message="Shadow\Utils">src/Controller.php:229</failure>
      <failure message="Third\Parser">src/bootstrap.php:317</failure>
    </testcase>
  </testsuite>
  <testsuite name="dev dependencies in production code" failures="1">
    <testcase name="some/package">
      <failure message="Another\Command">src/ProductGenerator.php:28</failure>
    </testcase>
  </testsuite>
  <testsuite name="prod dependencies used only in dev paths" failures="1">
    <testcase name="misplaced/package"></testcase>
  </testsuite>
  <testsuite name="unused dependencies" failures="1">
    <testcase name="dead/package"></testcase>
  </testsuite>
  <!-- showing all failure usages -->
</testsuites>
OUT;

        self::assertSame($this->normalizeEol($expectedRegularOutput), $regularOutput);
        self::assertSame($this->normalizeEol($expectedVerboseOutput), $verboseOutput);
        // editorconfig-checker-enable
    }

    protected function createFormatter(Printer $printer): ResultFormatter
    {
        return new JunitFormatter('/app', $printer);
    }

}
