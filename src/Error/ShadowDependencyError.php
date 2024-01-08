<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

use ShipMonk\Composer\Crate\ClassUsage;

class ShadowDependencyError implements SymbolError
{

    /**
     * @var string
     */
    private $packageName;

    /**
     * @var ClassUsage
     */
    private $exampleUsage;

    public function __construct(
        string $packageName,
        ClassUsage $exampleUsage
    )
    {
        $this->packageName = $packageName;
        $this->exampleUsage = $exampleUsage;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getExampleUsage(): ClassUsage
    {
        return $this->exampleUsage;
    }

}
