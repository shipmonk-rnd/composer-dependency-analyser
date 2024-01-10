<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

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
        $noPackagesError = 'No packages found';
        $parseError = 'Failure while parsing';

        $okOutput = 'No composer issues found';
        $helpOutput = 'Usage:';

        $this->runCommand('composer dump-autoload --classmap-authoritative', $rootDir, 0, 'Generated optimized autoload files');

        $this->runCommand('php bin/composer-dependency-analyser --ignore-unknown-classes', $rootDir, 0, $okOutput);
        $this->runCommand('php bin/composer-dependency-analyser --ignore-unknown-classes --verbose', $rootDir, 0, $okOutput);
        $this->runCommand('php ../bin/composer-dependency-analyser', $testsDir, 255, $noComposerJsonError);
        $this->runCommand('php bin/composer-dependency-analyser --help', $rootDir, 0, $helpOutput);
        $this->runCommand('php ../bin/composer-dependency-analyser --help', $testsDir, 0, $helpOutput);
        $this->runCommand('php bin/composer-dependency-analyser --ignore-unknown-classes --composer-json=composer.json', $rootDir, 0, $okOutput);
        $this->runCommand('php bin/composer-dependency-analyser --ignore-unknown-classes --composer-json=composer.lock', $rootDir, 255, $noPackagesError);
        $this->runCommand('php bin/composer-dependency-analyser --ignore-unknown-classes --composer-json=README.md', $rootDir, 255, $parseError);
        $this->runCommand('php ../bin/composer-dependency-analyser --ignore-unknown-classes --composer-json=composer.json', $testsDir, 255, $noComposerJsonError);
        $this->runCommand('php ../bin/composer-dependency-analyser --ignore-unknown-classes --composer-json=../composer.json', $testsDir, 0, $okOutput);
    }

    private function runCommand(
        string $command,
        string $cwd,
        int $expectedExitCode,
        string $expectedOutputContains
    ): void
    {
        $desc = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];

        $procHandle = proc_open($command, $desc, $pipes, $cwd);
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

        self::assertStringContainsString($expectedOutputContains, $output);
    }

}
