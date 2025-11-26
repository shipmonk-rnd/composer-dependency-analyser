<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config;

class PathToScan
{

    /**
     * @var string
     */
    private $path;

    /**
     * @var bool
     */
    private $isDev;

    public function __construct(
        string $path,
        bool $isDev
    )
    {
        $this->path = $path;
        $this->isDev = $isDev;
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
