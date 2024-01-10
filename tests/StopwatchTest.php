<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use LogicException;
use PHPUnit\Framework\TestCase;
use function usleep;

class StopwatchTest extends TestCase
{

    public function testStop(): void
    {
        $stopwatch = new Stopwatch();

        $stopwatch->start();
        usleep(100);
        $elapsed = $stopwatch->stop();

        self::assertGreaterThan(0, $elapsed);
    }

    public function testStopWithoutStart(): void
    {
        $stopwatch = new Stopwatch();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Stopwatch was not started');

        $stopwatch->stop();
    }

    public function testStartTwice(): void
    {
        $stopwatch = new Stopwatch();

        $stopwatch->start();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Stopwatch was already started');

        $stopwatch->start();
    }

}
