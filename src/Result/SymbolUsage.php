<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\SymbolKind;

class SymbolUsage
{

    /**
     * @param SymbolKind::* $kind
     */
    public function __construct(
        private string $filepath,
        private int $lineNumber,
        private int $kind,
    )
    {
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return SymbolKind::*
     */
    public function getKind(): int // @phpstan-ignore shipmonk.deadMethod
    {
        return $this->kind;
    }

}
