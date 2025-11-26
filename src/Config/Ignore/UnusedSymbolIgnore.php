<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use ShipMonk\ComposerDependencyAnalyser\SymbolKind;

class UnusedSymbolIgnore
{

    private string $unknownSymbol;

    private bool $isRegex;

    /**
     * @var SymbolKind::CLASSLIKE|SymbolKind::FUNCTION
     */
    private $symbolKind;

    /**
     * @param SymbolKind::CLASSLIKE|SymbolKind::FUNCTION $symbolKind
     */
    public function __construct(
        string $unknownSymbol,
        bool $isRegex,
        int $symbolKind,
    )
    {
        $this->unknownSymbol = $unknownSymbol;
        $this->isRegex = $isRegex;
        $this->symbolKind = $symbolKind;
    }

    public function getUnknownSymbol(): string
    {
        return $this->unknownSymbol;
    }

    public function isRegex(): bool
    {
        return $this->isRegex;
    }

    /**
     * @return SymbolKind::CLASSLIKE|SymbolKind::FUNCTION
     */
    public function getSymbolKind(): int
    {
        return $this->symbolKind;
    }

}
