<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Closure;
use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Result\AnalysisResult;
use ShipMonk\ComposerDependencyAnalyser\Result\JunitFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\SymbolUsage;
use function ob_get_clean;
use function ob_start;
use function str_replace;

class JunitFormatterTest extends TestCase
{

    public function testPrintResult(): void
    {
        $formatter = new JunitFormatter('/app', new Printer());

        $noIssuesOutput = $this->captureAndNormalizeOutput(static function () use ($formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [], []), new CliOptions(), new Configuration());
        });
        $noIssuesButUnusedIgnores = $this->captureAndNormalizeOutput(static function () use ($formatter): void {
            $formatter->format(new AnalysisResult(2, 0.123, [], [], [], [], [], [], [], [new UnusedErrorIgnore(ErrorType::SHADOW_DEPENDENCY, null, null)]), new CliOptions(), new Configuration());
        });

        $expectedNoIssuesOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?><testsuites></testsuites>
OUT;

        $expectedNoIssuesButWarningsOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="unused-ignore" failures="1"><testcase name="shadow-dependency"><failure>'shadow-dependency' was globally ignored, but it was never applied.</failure></testcase></testsuite></testsuites>
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

        $regularOutput = $this->captureAndNormalizeOutput(static function () use ($formatter, $analysisResult): void {
            $formatter->format($analysisResult, new CliOptions(), new Configuration());
        });
        $verboseOutput = $this->captureAndNormalizeOutput(static function () use ($formatter, $analysisResult): void {
            $options = new CliOptions();
            $options->verbose = true;
            $formatter->format($analysisResult, $options, new Configuration());
        });

        $expectedRegularOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="unknown classes" failures="1"><testcase name="Unknown\Thing"><failure>in app/init.php:1093</failure></testcase></testsuite><testsuite name="unknown functions" failures="1"><testcase name="Unknown\function"><failure>in app/foo.php:51</failure></testcase></testsuite><testsuite name="shadow dependencies" failures="2"><testcase name="shadow/another"><failure>e.g. Another\Controller in src/bootstrap.php:173</failure></testcase><testcase name="shadow/package"><failure>e.g. Forth\Provider in src/bootstrap.php:873 (+ 6 more)</failure></testcase></testsuite><testsuite name="dev dependencies in production code" failures="1"><testcase name="some/package"><failure>e.g. Another\Command in src/ProductGenerator.php:28</failure></testcase></testsuite><testsuite name="prod dependencies used only in dev paths" failures="1"><testcase name="misplaced/package"><failure></failure></testcase></testsuite><testsuite name="unused dependencies" failures="1"><testcase name="dead/package"><failure></failure></testcase></testsuite></testsuites>
OUT;
        $expectedVerboseOutput = <<<'OUT'
<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="unknown classes" failures="1"><testcase name="Unknown\Thing"><failure>app/init.php:1093</failure></testcase></testsuite><testsuite name="unknown functions" failures="1"><testcase name="Unknown\function"><failure>app/foo.php:51</failure></testcase></testsuite><testsuite name="shadow dependencies" failures="2"><testcase name="shadow/another"><failure>Another\Controller\n  src/bootstrap.php:173</failure></testcase><testcase name="shadow/package"><failure>Forth\Provider\n  src/bootstrap.php:873\nShadow\Comparator\n  src/Printer.php:25\nShadow\Utils\n  src/Utils.php:19\n  src/Utils.php:22\n  src/Application.php:128\n  + 1 more\n  + 1 more symbol</failure></testcase></testsuite><testsuite name="dev dependencies in production code" failures="1"><testcase name="some/package"><failure>Another\Command\n  src/ProductGenerator.php:28</failure></testcase></testsuite><testsuite name="prod dependencies used only in dev paths" failures="1"><testcase name="misplaced/package"><failure></failure></testcase></testsuite><testsuite name="unused dependencies" failures="1"><testcase name="dead/package"><failure></failure></testcase></testsuite></testsuites>
OUT;

        self::assertSame($this->normalizeEol($expectedRegularOutput), $regularOutput);
        self::assertSame($this->normalizeEol($expectedVerboseOutput), $verboseOutput);
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
