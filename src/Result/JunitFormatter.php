<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedClassIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Printer;
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

    public function __construct(string $cwd, Printer $printer)
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

        $classmapErrors = $result->getClassmapErrors();
        $shadowDependencyErrors = $result->getShadowDependencyErrors();
        $devDependencyInProductionErrors = $result->getDevDependencyInProductionErrors();
        $prodDependencyOnlyInDevErrors = $result->getProdDependencyOnlyInDevErrors();
        $unusedDependencyErrors = $result->getUnusedDependencyErrors();

        $maxShownUsages = $this->getMaxUsagesShownForErrors($options);

        if (count($classmapErrors) > 0) {
            $hasError = true;
            $xml .= $this->createClassBasedTestSuite(
                'unknown classes',
                $classmapErrors,
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

        $xml .= '</testsuites>';

        $this->printer->print($xml);

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
     */
    private function createClassBasedTestSuite(string $title, array $errors, int $maxShownUsages): string
    {
        $xml = sprintf('<testsuite name="%s" failures="%u">', $this->escape($title), count($errors));

        foreach ($errors as $classname => $usages) {
            $xml .= sprintf('<testcase name="%s">', $this->escape($classname));

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

                $xml .= sprintf('<failure>%s</failure>', $this->escape(implode('\n', $failureUsage)));
            } else {
                $firstUsage = $usages[0];
                $restUsagesCount = count($usages) - 1;
                $rest = $restUsagesCount > 0 ? " (+ {$restUsagesCount} more)" : '';
                $xml .= sprintf('<failure>in %s%s</failure>', $this->escape($this->relativizeUsage($firstUsage)), $rest);
            }

            $xml .= '</testcase>';
        }

        $xml .= '</testsuite>';

        return $xml;
    }

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $errors
     */
    private function createPackageBasedTestSuite(string $title, array $errors, int $maxShownUsages): string
    {
        $xml = sprintf('<testsuite name="%s" failures="%u">', $this->escape($title), count($errors));

        foreach ($errors as $packageName => $usagesPerClassname) {
            $xml .= sprintf('<testcase name="%s">', $this->escape($packageName));
            $xml .= sprintf('<failure>%s</failure>', $this->escape(implode('\n', $this->createUsages($usagesPerClassname, $maxShownUsages))));
            $xml .= '</testcase>';
        }

        $xml .= '</testsuite>';

        return $xml;
    }

    /**
     * @param array<string, list<SymbolUsage>> $usagesPerClassname
     * @return list<string>
     */
    private function createUsages(array $usagesPerClassname, int $maxShownUsages): array
    {
        $usageMessages = [];

        if ($maxShownUsages === 1) {
            $countOfAllUsages = array_reduce(
                $usagesPerClassname,
                static function (int $carry, array $usages): int {
                    return $carry + count($usages);
                },
                0
            );

            foreach ($usagesPerClassname as $classname => $usages) {
                $firstUsage = $usages[0];
                $restUsagesCount = $countOfAllUsages - 1;
                $rest = $countOfAllUsages > 1 ? " (+ {$restUsagesCount} more)" : '';
                $usageMessages[] = "e.g. {$classname} in {$this->relativizeUsage($firstUsage)}$rest";
                break;
            }
        } else {
            $classnamesPrinted = 0;

            foreach ($usagesPerClassname as $classname => $usages) {
                $classnamesPrinted++;

                $usageMessages[] = $classname;

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
                    $restClassnamesCount = count($usagesPerClassname) - $classnamesPrinted;

                    if ($restClassnamesCount > 0) {
                        $usageMessages[] = "  + {$restClassnamesCount} more class" . ($restClassnamesCount > 1 ? 'es' : '');
                        break;
                    }
                }
            }
        }

        return $usageMessages;
    }

    /**
     * @param list<UnusedClassIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    private function createUnusedIgnoresTestSuite(array $unusedIgnores): string
    {
        $xml = sprintf('<testsuite name="unused-ignore" failures="%u">', count($unusedIgnores));

        foreach ($unusedIgnores as $unusedIgnore) {
            if ($unusedIgnore instanceof UnusedClassIgnore) {
                $regex = $unusedIgnore->isRegex() ? ' regex' : '';
                $message = "Unknown class{$regex} '{$unusedIgnore->getUnknownClass()}' was ignored, but it was never applied.";
                $xml .= sprintf('<testcase name="%s"><failure>%s</failure></testcase>', $this->escape($unusedIgnore->getUnknownClass()), $this->escape($message));
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

}
