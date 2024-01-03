<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

class DevDependencyInProductionCodeError implements SymbolError
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

    public function __construct(
        string $className,
        string $packageName,
        string $exampleUsageFilepath
    )
    {
        $this->className = $className;
        $this->packageName = $packageName;
        $this->exampleUsageFilepath = $exampleUsageFilepath;
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

}
