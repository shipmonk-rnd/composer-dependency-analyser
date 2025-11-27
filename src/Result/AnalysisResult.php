<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedErrorIgnore;
use ShipMonk\ComposerDependencyAnalyser\Config\Ignore\UnusedSymbolIgnore;
use function ksort;
use function sort;

class AnalysisResult
{

    private int $scannedFilesCount;

    private float $elapsedTime;

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private array $usages = [];

    /**
     * @var array<string, list<SymbolUsage>>
     */
    private array $unknownClassErrors;

    /**
     * @var array<string, list<SymbolUsage>>
     */
    private array $unknownFunctionErrors;

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private array $shadowDependencyErrors = [];

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private array $devDependencyInProductionErrors = [];

    /**
     * @var list<string>
     */
    private array $prodDependencyOnlyInDevErrors;

    /**
     * @var list<string>
     */
    private array $unusedDependencyErrors;

    /**
     * @var list<UnusedSymbolIgnore|UnusedErrorIgnore>
     */
    private array $unusedIgnores;

    /**
     * @param array<string, array<string, list<SymbolUsage>>> $usages package => [ classname => usage[] ]
     * @param array<string, list<SymbolUsage>> $unknownClassErrors package => usages
     * @param array<string, list<SymbolUsage>> $unknownFunctionErrors package => usages
     * @param array<string, array<string, list<SymbolUsage>>> $shadowDependencyErrors package => [ classname => usage[] ]
     * @param array<string, array<string, list<SymbolUsage>>> $devDependencyInProductionErrors package => [ classname => usage[] ]
     * @param list<string> $prodDependencyOnlyInDevErrors package[]
     * @param list<string> $unusedDependencyErrors package[]
     * @param list<UnusedSymbolIgnore|UnusedErrorIgnore> $unusedIgnores
     */
    public function __construct(
        int $scannedFilesCount,
        float $elapsedTime,
        array $usages,
        array $unknownClassErrors,
        array $unknownFunctionErrors,
        array $shadowDependencyErrors,
        array $devDependencyInProductionErrors,
        array $prodDependencyOnlyInDevErrors,
        array $unusedDependencyErrors,
        array $unusedIgnores,
    )
    {
        ksort($usages);
        ksort($unknownClassErrors);
        ksort($unknownFunctionErrors);
        ksort($shadowDependencyErrors);
        ksort($devDependencyInProductionErrors);
        sort($prodDependencyOnlyInDevErrors);
        sort($unusedDependencyErrors);

        $this->scannedFilesCount = $scannedFilesCount;
        $this->elapsedTime = $elapsedTime;
        $this->unknownClassErrors = $unknownClassErrors;
        $this->unknownFunctionErrors = $unknownFunctionErrors;

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
    public function getUnknownClassErrors(): array
    {
        return $this->unknownClassErrors;
    }

    /**
     * @return array<string, list<SymbolUsage>>
     */
    public function getUnknownFunctionErrors(): array
    {
        return $this->unknownFunctionErrors;
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
     * @return list<UnusedSymbolIgnore|UnusedErrorIgnore>
     */
    public function getUnusedIgnores(): array
    {
        return $this->unusedIgnores;
    }

}
