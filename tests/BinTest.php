<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use function fclose;
use function proc_close;
use function proc_open;
use function stream_get_contents;

class BinTest extends TestCase
{

    public function test(): void
    {
        $rootDir = __DIR__ . '/..';
        $testsDir = __DIR__;

        $noComposerJsonError = 'File composer.json not found';
        $notInClassmapError = 'not found in classmap';
        $noPackagesError = 'No packages found';
        $parseError = 'Failure while parsing';

        $okOutput = 'No shadow dependencies found';
        $helpOutput = 'Usage:';

        $this->runCommand(__DIR__ . '/../bin/composer-analyser src', $rootDir, 0, $okOutput);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser src', $testsDir, 255, $noComposerJsonError);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --help', $rootDir, 0, $helpOutput);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --help', $testsDir, 0, $helpOutput);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --composer_json=composer.json src', $rootDir, 0, $okOutput);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --composer_json=composer.lock src', $rootDir, 255, $noPackagesError);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --composer_json=README.md src', $rootDir, 255, $parseError);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --composer_json=composer.json src', $testsDir, 255, $noComposerJsonError);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser --composer_json=../composer.json ../src', $testsDir, 0, $okOutput);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser tests', $rootDir, 255, $notInClassmapError);
        $this->runCommand(__DIR__ . '/../bin/composer-analyser tests', $testsDir, 255, $noComposerJsonError);
    }

    private function runCommand(
        string $command,
        string $cwd,
        int $expectedExitCode,
        ?string $expectedOutputContains = null
    ): void
    {
        $desc = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];

        $procHandle = proc_open('php ' . $command, $desc, $pipes, $cwd);
        self::assertNotFalse($procHandle);

        /** @var list<resource> $pipes */
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        self::assertNotFalse($output);
        self::assertNotFalse($errorOutput);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($procHandle);
        self::assertSame(
            $expectedExitCode,
            $exitCode,
            "Output was:\n" . $output . "\n" .
            "Error was:\n" . $errorOutput . "\n"
        );

        if ($expectedOutputContains !== null) {
            self::assertStringContainsString($expectedOutputContains, $output);
        }
    }

}
