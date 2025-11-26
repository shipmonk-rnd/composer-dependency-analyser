<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

class UnusedErrorIgnore
{

    /**
     * @param ErrorType::* $errorType
     */
    public function __construct(
        private string $errorType,
        private ?string $filePath,
        private ?string $package,
    )
    {
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getPath(): ?string
    {
        return $this->filePath;
    }

    public function getPackage(): ?string
    {
        return $this->package;
    }

}
