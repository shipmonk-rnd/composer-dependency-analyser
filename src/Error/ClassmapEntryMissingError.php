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

    public function __construct(
        string $className,
        string $exampleUsageFilepath
    )
    {
        $this->className = $className;
        $this->exampleUsageFilepath = $exampleUsageFilepath;
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

}
