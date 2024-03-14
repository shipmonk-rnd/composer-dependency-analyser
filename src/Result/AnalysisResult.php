<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedClassIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use function ksort;
use function sort;

class AnalysisResult
{

    /**
     * @var int
     */
    private $scannedFilesCount;

    /**
     * @var float
     */
    private $elapsedTime;

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private $usages;

    /**
     * @var array<string, list<SymbolUsage>>
     */
    private $classmapErrors = [];

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private $shadowDependencyErrors = [];

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private $devDependencyInProductionErrors = [];

    /**
     * @var list<string>
     */
    private $prodDependencyOnlyInDevErrors;

    /**
     * @var list<string>
     */
    private $unusedDependencyErrors;

    /**
     * @var list<UnusedClassIgnore|UnusedErrorIgnore>
     */
    private $unusedIgnores;

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages package => [ classname => usage[] ]
     * @param array<string, list<SymbolUsage>> $classmapErrors package => usages
     * @param array<string, array<string, list<SymbolUsage>>> $shadowDependencyErrors package => [ classname => usage[] ]
     * @param array<string, array<string, list<SymbolUsage>>> $devDependencyInProductionErrors package => [ classname => usage[] ]
     * @param list<string> $prodDependencyOnlyInDevErrors package[]
     * @param list<string> $unusedDependencyErrors package[]
     * @param list<UnusedClassIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    public function __construct(
        int $scannedFilesCount,
        float $elapsedTime,
        array $usages,
        array $classmapErrors,
        array $shadowDependencyErrors,
        array $devDependencyInProductionErrors,
        array $prodDependencyOnlyInDevErrors,
        array $unusedDependencyErrors,
        array $unusedIgnores
    )
    {
        ksort($usages);
        ksort($classmapErrors);
        ksort($shadowDependencyErrors);
        ksort($devDependencyInProductionErrors);
        sort($prodDependencyOnlyInDevErrors);
        sort($unusedDependencyErrors);

        $this->scannedFilesCount = $scannedFilesCount;
        $this->elapsedTime = $elapsedTime;
        $this->classmapErrors = $classmapErrors;

        foreach ($usages as $package => $classes) {
            ksort($classes);
            $this->usages[$package] = $classes;
        }

        foreach ($shadowDependencyErrors as $package => $classes) {
            ksort($classes);
            $this->shadowDependencyErrors[$package] = $classes;
        }

        foreach ($devDependencyInProductionErrors as $package => $classes) {
            ksort($classes);
            $this->devDependencyInProductionErrors[$package] = $classes;
        }

        $this->prodDependencyOnlyInDevErrors = $prodDependencyOnlyInDevErrors;
        $this->unusedDependencyErrors = $unusedDependencyErrors;
        $this->unusedIgnores = $unusedIgnores;
    }

    public function getScannedFilesCount(): int
    {
        return $this->scannedFilesCount;
    }

    public function getElapsedTime(): float
    {
        return $this->elapsedTime;
    }

    /**
     * @return array<string, array<string, list<SymbolUsage>>>
     */
    public function getUsages(): array
    {
        return $this->usages;
    }

    /**
     * @return array<string, list<SymbolUsage>>
     */
    public function getClassmapErrors(): array
    {
        return $this->classmapErrors;
    }

    /**
     * @return array<string, array<string, list<SymbolUsage>>>
     */
    public function getShadowDependencyErrors(): array
    {
        return $this->shadowDependencyErrors;
    }

    /**
     * @return array<string, array<string, list<SymbolUsage>>>
     */
    public function getDevDependencyInProductionErrors(): array
    {
        return $this->devDependencyInProductionErrors;
    }

    /**
     * @return list<string>
     */
    public function getProdDependencyOnlyInDevErrors(): array
    {
        return $this->prodDependencyOnlyInDevErrors;
    }

    /**
     * @return list<string>
     */
    public function getUnusedDependencyErrors(): array
    {
        return $this->unusedDependencyErrors;
    }

    /**
     * @return list<UnusedClassIgnore|UnusedErrorIgnore>
     */
    public function getUnusedIgnores(): array
    {
        return $this->unusedIgnores;
    }

    public function hasNoErrors(): bool
    {
        return $this->unusedDependencyErrors === []
            && $this->classmapErrors === []
            && $this->devDependencyInProductionErrors === []
            && $this->prodDependencyOnlyInDevErrors === []
            && $this->shadowDependencyErrors === [];
    }

}
