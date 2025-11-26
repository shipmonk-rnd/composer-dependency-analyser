<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

class PathToScan
{

    public function __construct(
        private string $path,
        private bool $isDev,
    )
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isDev(): bool
    {
        return $this->isDev;
    }

}
