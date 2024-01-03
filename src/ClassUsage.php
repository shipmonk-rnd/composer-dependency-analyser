<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

class ClassUsage
{

    /**
     * @var string
     */
    private $classname;

    /**
     * @var string
     */
    private $filepath;

    /**
     * @var int
     */
    private $lineNumber;

    public function __construct(string $classname, string $filepath, int $lineNumber)
    {
        $this->classname = $classname;
        $this->filepath = $filepath;
        $this->lineNumber = $lineNumber;
    }

    public function getClassname(): string
    {
        return $this->classname;
    }

    public function getFilepath(): string
    {
        return $this->filepath;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

}
