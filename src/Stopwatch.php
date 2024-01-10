<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use LogicException;
use function microtime;

class Stopwatch
{

    /**
     * @var float|null
     */
    private $startTime;

    public function start(): void
    {
        if ($this->startTime !== null) {
            throw new LogicException('Stopwatch was already started');
        }

        $this->startTime = microtime(true);
    }

    public function stop(): float
    {
        if ($this->startTime === null) {
            throw new LogicException('Stopwatch was not started');
        }

        $elapsed = microtime(true) - $this->startTime;
        $this->startTime = null;
        return $elapsed;
    }

}
