<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\SymbolKind;

class SymbolUsage
{

    /**
     * @var string
     */
    private $filepath;

    /**
     * @var int
     */
    private $lineNumber;

    /**
     * @var SymbolKind::*
     */
    private $kind;

    /**
     * @param SymbolKind::* $kind
     */
    public function __construct(
        string $filepath,
        int $lineNumber,
        int $kind
    )
    {
        $this->filepath = $filepath;
        $this->lineNumber = $lineNumber;
        $this->kind = $kind;
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
    public function getKind(): int
    {
        return $this->kind;
    }

}
