<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use DOMDocument;
use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
use ShipMonk\ComposerDependencyAnalyser\SymbolKind;
use function array_fill_keys;
use function count;
use function extension_loaded;
use function htmlspecialchars;
use function sprintf;
use function strlen;
use function strpos;
use function substr;
use function trim;
use const ENT_COMPAT;
use const ENT_XML1;
use const LIBXML_NOEMPTYTAG;
use const PHP_INT_MAX;

class JunitFormatter implements ResultFormatter
{

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var Printer
     */
    private $printer;

    public function __construct(
        string $cwd,
        Printer $printer
    )
    {
        $this->cwd = $cwd;
        $this->printer = $printer;
    }

    public function format(
        AnalysisResult $result,
        CliOptions $options,
        Configuration $configuration
    ): int
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<testsuites>';

        $hasError = false;
        $unusedIgnores = $result->getUnusedIgnores();

        $unknownClassErrors = $result->getUnknownClassErrors();
        $unknownFunctionErrors = $result->getUnknownFunctionErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $prodDependencyOnlyInDevErrors = $result->getProdDependencyOnlyInDevErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();

        $maxShownUsages = $this->getMaxUsagesShownForErrors($options);

        if (count($unknownClassErrors) > 0) {
            $hasError = true;
            $xml .= $this->createSymbolBasedTestSuite(
                'unknown classes',
                $unknownClassErrors,
                $maxShownUsages
            );
        }

        if (count($unknownFunctionErrors) > 0) {
            $hasError = true;
            $xml .= $this->createSymbolBasedTestSuite(
                'unknown functions',
                $unknownFunctionErrors,
                $maxShownUsages
            );
        }

        if (count($shadowDependencyErrors) > 0) {
            $hasError = true;
            $xml .= $this->createPackageBasedTestSuite(
                'shadow dependencies',
                $shadowDependencyErrors,
                $maxShownUsages
            );
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $hasError = true;
            $xml .= $this->createPackageBasedTestSuite(
                'dev dependencies in production code',
                $devDependencyInProductionErrors,
                $maxShownUsages
            );
        }

        if (count($prodDependencyOnlyInDevErrors) > 0) {
            $hasError = true;
            $xml .= $this->createPackageBasedTestSuite(
                'prod dependencies used only in dev paths',
                array_fill_keys($prodDependencyOnlyInDevErrors, []),
                $maxShownUsages
            );
        }

        if (count($unusedDependencyErrors) > 0) {
            $hasError = true;
            $xml .= $this->createPackageBasedTestSuite(
                'unused dependencies',
                array_fill_keys($unusedDependencyErrors, []),
                $maxShownUsages
            );
        }

        if ($unusedIgnores !== [] && $configuration->shouldReportUnmatchedIgnoredErrors()) {
            $hasError = true;
            $xml .= $this->createUnusedIgnoresTestSuite($unusedIgnores);
        }

        if ($hasError) {
            $xml .= sprintf('<!-- %s -->', $this->getUsagesComment($maxShownUsages));
        }

        $xml .= '</testsuites>';

        $this->printer->print($this->prettyPrintXml($xml));

        if ($hasError) {
            return 1;
        }

        return 0;
    }

    private function getMaxUsagesShownForErrors(CliOptions $options): int
    {
        if ($options->showAllUsages === true) {
            return PHP_INT_MAX;
        }

        if ($options->verbose === true) {
            return self::VERBOSE_SHOWN_USAGES;
        }

        return 1;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     */
    private function createSymbolBasedTestSuite(
        string $title,
        array $errors,
        int $maxShownUsages
    ): string
    {
        $xml = sprintf('<testsuite name="%s" failures="%u">', $this->escape($title), count($errors));

        foreach ($errors as $symbol => $usages) {
            $xml .= sprintf('<testcase name="%s">', $this->escape($symbol));

            foreach ($usages as $index => $usage) {
                $xml .= sprintf('<failure>%s</failure>', $this->escape($this->relativizeUsage($usage)));

                if ($index === $maxShownUsages - 1) {
                    break;
                }
            }

            $xml .= '</testcase>';
        }

        $xml .= '</testsuite>';

        return $xml;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $errors
     */
    private function createPackageBasedTestSuite(
        string $title,
        array $errors,
        int $maxShownUsages
    ): string
    {
        $xml = sprintf('<testsuite name="%s" failures="%u">', $this->escape($title), count($errors));

        foreach ($errors as $packageName => $usagesPerClassname) {
            $xml .= sprintf('<testcase name="%s">', $this->escape($packageName));

            $printedSymbols = 0;

            foreach ($usagesPerClassname as $symbol => $usages) {
                foreach ($this->createUsages($usages, $maxShownUsages) as $usage) {
                    $printedSymbols++;
                    $xml .= sprintf(
                        '<failure message="%s">%s</failure>',
                        $symbol,
                        $this->escape($usage)
                    );

                    if ($printedSymbols === $maxShownUsages) {
                        break 2;
                    }
                }
            }

            $xml .= '</testcase>';
        }

        $xml .= '</testsuite>';

        return $xml;
    }

    /**
     * @param list<SymbolUsage> $usages
     * @return list<string>
     */
    private function createUsages(
        array $usages,
        int $maxShownUsages
    ): array
    {
        $usageMessages = [];

        foreach ($usages as $index => $usage) {
            $usageMessages[] = $this->relativizeUsage($usage);

            if ($index === $maxShownUsages - 1) {
                break;
            }
        }

        return $usageMessages;
    }

    /**
     * @param list<UnusedSymbolIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    private function createUnusedIgnoresTestSuite(array $unusedIgnores): string
    {
        $xml = sprintf('<testsuite name="unused-ignore" failures="%u">', count($unusedIgnores));

        foreach ($unusedIgnores as $unusedIgnore) {
            if ($unusedIgnore instanceof UnusedSymbolIgnore) {
                $kind = $unusedIgnore->getSymbolKind() === SymbolKind::CLASSLIKE ? 'class' : 'function';
                $regex = $unusedIgnore->isRegex() ? ' regex' : '';
                $message = "Unknown {$kind}{$regex} '{$unusedIgnore->getUnknownSymbol()}' was ignored, but it was never applied.";
                $xml .= sprintf('<testcase name="%s"><failure>%s</failure></testcase>', $this->escape($unusedIgnore->getUnknownSymbol()), $this->escape($message));
            } else {
                $package = $unusedIgnore->getPackage();
                $path = $unusedIgnore->getPath();
                $message = "'{$unusedIgnore->getErrorType()}'";

                if ($package === null && $path === null) {
                    $message = "'{$unusedIgnore->getErrorType()}' was globally ignored, but it was never applied.";
                }

                if ($package !== null && $path === null) {
                    $message = "'{$unusedIgnore->getErrorType()}' was ignored for package '{$package}', but it was never applied.";
                }

                if ($package === null && $path !== null) {
                    $message = "'{$unusedIgnore->getErrorType()}' was ignored for path '{$this->relativizePath($path)}', but it was never applied.";
                }

                if ($package !== null && $path !== null) {
                    $message = "'{$unusedIgnore->getErrorType()}' was ignored for package '{$package}' and path '{$this->relativizePath($path)}', but it was never applied.";
                }

                $xml .= sprintf('<testcase name="%s"><failure>%s</failure></testcase>', $this->escape($unusedIgnore->getErrorType()), $this->escape($message));
            }
        }

        return $xml . '</testsuite>';
    }

    private function relativizeUsage(SymbolUsage $usage): string
    {
        return "{$this->relativizePath($usage->getFilepath())}:{$usage->getLineNumber()}";
    }

    private function relativizePath(string $path): string
    {
        if (strpos($path, $this->cwd) === 0) {
            return (string) substr($path, strlen($this->cwd) + 1);
        }

        return $path;
    }

    private function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function prettyPrintXml(string $inputXml): string
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            return $inputXml;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($inputXml);

        $outputXml = $dom->saveXML(null, LIBXML_NOEMPTYTAG);

        if ($outputXml === false) {
            return $inputXml;
        }

        return trim($outputXml);
    }

    private function getUsagesComment(int $maxShownUsages): string
    {
        if ($maxShownUsages === PHP_INT_MAX) {
            return 'showing all failure usages';
        }

        if ($maxShownUsages === 1) {
            return 'showing only first example failure usage';
        }

        return sprintf('showing only first %d example failure usages', $maxShownUsages);
    }

}
