<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Result;

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

    public function __construct(string $filepath, int $lineNumber)
    {
        $this->filepath = $filepath;
        $this->lineNumber = $lineNumber;
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

}
