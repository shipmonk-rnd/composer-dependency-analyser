<?php declare(strict_types = 1);

namespace ShipMonk\Composer\Error;

interface SymbolError
{

    public function getPackageName(): ?string;

    public function getSymbolName(): string;

    public function getExampleUsageFilepath(): string;

}
