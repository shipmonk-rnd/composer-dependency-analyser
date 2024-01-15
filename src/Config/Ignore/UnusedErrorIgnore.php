<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

class UnusedErrorIgnore
{

    /**
     * @var ErrorType::*
     */
    private $errorType;

    /**
     * @var string|null
     */
    private $filePath;

    /**
     * @var string|null
     */
    private $package;

    /**
     * @param ErrorType::* $errorType
     */
    public function __construct(string $errorType, ?string $filePath, ?string $package)
    {
        $this->errorType = $errorType;
        $this->filePath = $filePath;
        $this->package = $package;
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
