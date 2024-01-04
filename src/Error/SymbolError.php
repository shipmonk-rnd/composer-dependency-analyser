<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

use ShipMonk\Composer\ClassUsage;

interface SymbolError
{

    public function getPackageName(): ?string;

    public function getExampleUsage(): ?ClassUsage;

}
