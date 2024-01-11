<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

class UnusedClassIgnore
{

    /**
     * @var string
     */
    private $unknownClass;

    /**
     * @var bool
     */
    private $isRegex;

    public function __construct(string $unknownClass, bool $isRegex)
    {
        $this->unknownClass = $unknownClass;
        $this->isRegex = $isRegex;
    }

    public function getUnknownClass(): string
    {
        return $this->unknownClass;
    }

    public function isRegex(): bool
    {
        return $this->isRegex;
    }

}
