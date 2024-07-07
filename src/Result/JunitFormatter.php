<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use DOMException;
use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
use ShipMonk\ComposerDependencyAnalyser\SymbolKind;
use function array_fill_keys;
use function array_reduce;
use function count;
use function htmlspecialchars;
use function implode;
use function sprintf;
use function strlen;
use function strpos;
use function substr;
use const ENT_COMPAT;
use const ENT_XML1;
use const LIBXML_NOEMPTYTAG;
use const PHP_INT_MAX;

final class JunitFormatter extends AbstractXmlFormatter implements ResultFormatter
{

    /**
     * @var string
     */
    private $cwd;

    /**
     * @throws DOMException
     */
    public function __construct(string $cwd, Printer $printer, ?bool $verbose = null)
    {
        if ($verbose === null) {
            $verbose = false;
        }

        parent::__construct($printer, $verbose);
        $this->cwd = $cwd;
        $this->rootElement = $this->document->createElement('testsuites');
        $this->document->appendChild($this->rootElement);
    }

    /**
     * @throws DOMException
     */
    public function format(
        AnalysisResult $result,
        CliOptions $options,
        Configuration $configuration
    ): int
    {
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
            $this->createSymbolBasedTestSuite(
                'unknown classes',
                $unknownClassErrors,
                $maxShownUsages
            );
        }

        if (count($unknownFunctionErrors) > 0) {
            $hasError = true;
            $this->createSymbolBasedTestSuite(
                'unknown functions',
                $unknownFunctionErrors,
                $maxShownUsages
            );
        }

        if (count($shadowDependencyErrors) > 0) {
            $hasError = true;
            $this->createPackageBasedTestSuite(
                'shadow dependencies',
                $shadowDependencyErrors,
                $maxShownUsages
            );
        }

        if (count($devDependencyInProductionErrors) > 0) {
            $hasError = true;
            $this->createPackageBasedTestSuite(
                'dev dependencies in production code',
                $devDependencyInProductionErrors,
                $maxShownUsages
            );
        }

        if (count($prodDependencyOnlyInDevErrors) > 0) {
            $hasError = true;
            $this->createPackageBasedTestSuite(
                'prod dependencies used only in dev paths',
                array_fill_keys($prodDependencyOnlyInDevErrors, []),
                $maxShownUsages
            );
        }

        if (count($unusedDependencyErrors) > 0) {
            $hasError = true;
            $this->createPackageBasedTestSuite(
                'unused dependencies',
                array_fill_keys($unusedDependencyErrors, []),
                $maxShownUsages
            );
        }

        if ($unusedIgnores !== [] && $configuration->shouldReportUnmatchedIgnoredErrors()) {
            $hasError = true;
            $this->createUnusedIgnoresTestSuite($unusedIgnores);
        }

        $xmlString = $this->document->saveXML(null, LIBXML_NOEMPTYTAG);

        if ($xmlString === false) {
            $xmlString = '';
        }

        $this->printer->print($xmlString);

        if ($hasError) {
            return 255;
        }

        return 0;
    }

    private function getMaxUsagesShownForErrors(CliOptions $options): int
    {
        if ($options->verbose === true) {
            return self::VERBOSE_SHOWN_USAGES;
        }

        if ($options->showAllUsages === true) {
            return PHP_INT_MAX;
        }

        return 1;
    }

    /**
     * @param array<string, list<SymbolUsage>> $errors
     * @throws DOMException
     */
    private function createSymbolBasedTestSuite(string $title, array $errors, int $maxShownUsages): void
    {
        $testsuite = $this->document->createElement('testsuite');
        $testsuite->setAttribute('name', $this->escape($title));
        $testsuite->setAttribute('failures', sprintf('%u', count($errors)));

        $this->rootElement->appendChild($testsuite);

        foreach ($errors as $symbol => $usages) {
            $testcase = $this->document->createElement('testcase');
            $testcase->setAttribute('name', $this->escape($symbol));
            $testsuite->appendChild($testcase);

            if ($maxShownUsages > 1) {
                $failureUsage = [];

                foreach ($usages as $index => $usage) {
                    $failureUsage[] = $this->relativizeUsage($usage);

                    if ($index === $maxShownUsages - 1) {
                        $restUsagesCount = count($usages) - $index - 1;

                        if ($restUsagesCount > 0) {
                            $failureUsage[] = "+ {$restUsagesCount} more";
                            break;
                        }
                    }
                }

                $failureMessage = $this->escape(implode('\n', $failureUsage));
            } else {
                $firstUsage = $usages[0];
                $restUsagesCount = count($usages) - 1;
                $rest = $restUsagesCount > 0 ? " (+ {$restUsagesCount} more)" : '';

                $failureMessage = sprintf('in %s%s', $this->escape($this->relativizeUsage($firstUsage)), $rest);
            }

            $failure = $this->document->createElement('failure', $failureMessage);
            $testcase->appendChild($failure);
        }
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $errors
     * @throws DOMException
     */
    private function createPackageBasedTestSuite(string $title, array $errors, int $maxShownUsages): void
    {
        $testsuite = $this->document->createElement('testsuite');
        $testsuite->setAttribute('name', $this->escape($title));
        $testsuite->setAttribute('failures', sprintf('%u', count($errors)));

        $this->rootElement->appendChild($testsuite);

        foreach ($errors as $packageName => $usagesPerClassname) {
            $testcase = $this->document->createElement('testcase');
            $testcase->setAttribute('name', $this->escape($packageName));
            $testsuite->appendChild($testcase);

            $failure = $this->document->createElement(
                'failure',
                $this->escape(implode('\n', $this->createUsages($usagesPerClassname, $maxShownUsages)))
            );
            $testcase->appendChild($failure);
        }
    }

    /**
     * @param array<string, list<SymbolUsage>> $usagesPerSymbol
     * @return list<string>
     */
    private function createUsages(array $usagesPerSymbol, int $maxShownUsages): array
    {
        $usageMessages = [];

        if ($maxShownUsages === 1) {
            $countOfAllUsages = array_reduce(
                $usagesPerSymbol,
                static function (int $carry, array $usages): int {
                    return $carry + count($usages);
                },
                0
            );

            foreach ($usagesPerSymbol as $symbol => $usages) {
                $firstUsage = $usages[0];
                $restUsagesCount = $countOfAllUsages - 1;
                $rest = $countOfAllUsages > 1 ? " (+ {$restUsagesCount} more)" : '';
                $usageMessages[] = "e.g. {$symbol} in {$this->relativizeUsage($firstUsage)}$rest";
                break;
            }
        } else {
            $classnamesPrinted = 0;

            foreach ($usagesPerSymbol as $symbol => $usages) {
                $classnamesPrinted++;

                $usageMessages[] = $symbol;

                foreach ($usages as $index => $usage) {
                    $usageMessages[] = "  {$this->relativizeUsage($usage)}";

                    if ($index === $maxShownUsages - 1) {
                        $restUsagesCount = count($usages) - $index - 1;

                        if ($restUsagesCount > 0) {
                            $usageMessages[] = "  + {$restUsagesCount} more";
                            break;
                        }
                    }
                }

                if ($classnamesPrinted === $maxShownUsages) {
                    $restSymbolsCount = count($usagesPerSymbol) - $classnamesPrinted;

                    if ($restSymbolsCount > 0) {
                        $usageMessages[] = "  + {$restSymbolsCount} more symbol" . ($restSymbolsCount > 1 ? 's' : '');
                        break;
                    }
                }
            }
        }

        return $usageMessages;
    }

    /**
     * @param list<UnusedSymbolIgnore|UnusedErrorIgnore> $unusedIgnores
     * @throws DOMException
     */
    private function createUnusedIgnoresTestSuite(array $unusedIgnores): void
    {
        $testsuite = $this->document->createElement('testsuite');
        $testsuite->setAttribute('name', 'unused-ignore');
        $testsuite->setAttribute('failures', sprintf('%u', count($unusedIgnores)));

        $this->rootElement->appendChild($testsuite);

        foreach ($unusedIgnores as $unusedIgnore) {
            if ($unusedIgnore instanceof UnusedSymbolIgnore) {
                $kind = $unusedIgnore->getSymbolKind() === SymbolKind::CLASSLIKE ? 'class' : 'function';
                $regex = $unusedIgnore->isRegex() ? ' regex' : '';
                $message = "Unknown {$kind}{$regex} '{$unusedIgnore->getUnknownSymbol()}' was ignored, but it was never applied.";

                $testcaseName = $this->escape($unusedIgnore->getUnknownSymbol());
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

                $testcaseName = $this->escape($unusedIgnore->getErrorType());
            }

            $testcase = $this->document->createElement('testcase');
            $testcase->setAttribute('name', $testcaseName);
            $testsuite->appendChild($testcase);

            $failure = $this->document->createElement('failure', $this->escape($message));
            $testcase->appendChild($failure);
        }
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

}
