<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

use ShipMonk\Composer\Crate\ClassUsage;

class ClassmapEntryMissingError implements SymbolError
{

    /**
     * @var ClassUsage
     */
    private $exampleUsage;

    public function __construct(
        ClassUsage $exampleUsage
    )
    {
        $this->exampleUsage = $exampleUsage;
    }

    public function getPackageName(): ?string
    {
        return null;
    }

    public function getExampleUsage(): ClassUsage
    {
        return $this->exampleUsage;
    }

}
