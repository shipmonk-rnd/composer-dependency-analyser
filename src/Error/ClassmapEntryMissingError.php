<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

class ClassmapEntryMissingError implements SymbolError
{

    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $exampleUsageFilepath;

    /**
     * @var int
     */
    private $exampleUsageLine;

    public function __construct(
        string $className,
        string $exampleUsageFilepath,
        int $exampleUsageLine
    )
    {
        $this->className = $className;
        $this->exampleUsageFilepath = $exampleUsageFilepath;
        $this->exampleUsageLine = $exampleUsageLine;
    }

    public function getSymbolName(): string
    {
        return $this->className;
    }

    public function getExampleUsageFilepath(): string
    {
        return $this->exampleUsageFilepath;
    }

    public function getPackageName(): ?string
    {
        return null;
    }

    public function getExampleUsageLine(): int
    {
        return $this->exampleUsageLine;
    }

}
