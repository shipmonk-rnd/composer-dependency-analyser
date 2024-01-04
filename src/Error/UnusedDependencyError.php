<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

use ShipMonk\Composer\ClassUsage;

class UnusedDependencyError implements SymbolError
{

    /**
     * @var string
     */
    private $packageName;

    public function __construct(string $packageName)
    {
        $this->packageName = $packageName;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getExampleUsage(): ?ClassUsage
    {
        return null;
    }

}
