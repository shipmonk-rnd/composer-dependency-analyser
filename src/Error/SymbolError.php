<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

use ShipMonk\Composer\Crate\ClassUsage;

interface SymbolError
{

    public function getPackageName(): ?string;

    public function getExampleUsage(): ?ClassUsage;

}
