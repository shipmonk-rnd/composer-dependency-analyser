<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Result;

class AnalysisResult
{

    /**
     * @var array<string, list<SymbolUsage>>
     */
    private $classmapErrors;

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private $shadowDependencyErrors;

    /**
     * @var array<string, array<string, list<SymbolUsage>>>
     */
    private $devDependencyInProductionErrors;

    /**
     * @var list<string>
     */
    private $unusedDependencyErrors;

    /**
     * @param array<string, list<SymbolUsage>> $classmapErrors package => [ usage[] ]
     * @param array<string, array<string, list<SymbolUsage>>> $shadowDependencyErrors package => [ classname => usage[] ]
     * @param array<string, array<string, list<SymbolUsage>>> $devDependencyInProductionErrors package => [ classname => usage[] ]
     * @param list<string> $unusedDependencyErrors package[]
     */
    public function __construct(
        array $classmapErrors,
        array $shadowDependencyErrors,
        array $devDependencyInProductionErrors,
        array $unusedDependencyErrors
    )
    {
        $this->classmapErrors = $classmapErrors;
        $this->shadowDependencyErrors = $shadowDependencyErrors;
        $this->devDependencyInProductionErrors = $devDependencyInProductionErrors;
        $this->unusedDependencyErrors = $unusedDependencyErrors;
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
    public function getUnusedDependencyErrors(): array
    {
        return $this->unusedDependencyErrors;
    }

    public function hasNoErrors(): bool
    {
        return $this->unusedDependencyErrors === []
            && $this->classmapErrors === []
            && $this->devDependencyInProductionErrors === []
            && $this->shadowDependencyErrors === [];
    }

}
