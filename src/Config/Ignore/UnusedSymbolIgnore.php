<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use ShipMonk\ComposerDependencyAnalyser\SymbolKind;

class UnusedSymbolIgnore
{

    /**
     * @param SymbolKind::CLASSLIKE|SymbolKind::FUNCTION $symbolKind
     */
    public function __construct(
        private string $unknownSymbol,
        private bool $isRegex,
        private int $symbolKind,
    )
    {
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
