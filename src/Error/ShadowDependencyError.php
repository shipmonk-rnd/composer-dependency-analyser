<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

class ShadowDependencyError implements SymbolError
{

    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $packageName;

    /**
     * @var string
     */
    private $exampleUsageFilepath;

    private int $exampleUsageLine;

    public function __construct(
        string $className,
        string $packageName,
        string $exampleUsageFilepath,
        int $exampleUsageLine
    )
    {
        $this->className = $className;
        $this->packageName = $packageName;
        $this->exampleUsageFilepath = $exampleUsageFilepath;
        $this->exampleUsageLine = $exampleUsageLine;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getSymbolName(): string
    {
        return $this->className;
    }

    public function getExampleUsageFilepath(): string
    {
        return $this->exampleUsageFilepath;
    }

    public function getExampleUsageLine(): int
    {
        return $this->exampleUsageLine;
    }

}
