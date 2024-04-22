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
        $junitDumpError = "Cannot use 'junit' format with '--dump-usages' option";

        $okOutput = 'No composer issues found';
        $dumpingOutput = 'Dumping sample usages of';
        $helpOutput = 'Usage:';

        $usingConfig = 'Using config';

        $junitOutput = '<?xml version="1.0" encoding="UTF-8"?><testsuites></testsuites>';

        $this->runCommand('php bin/composer-dependency-analyser', $rootDir, 0, $okOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --verbose', $rootDir, 0, $okOutput, $usingConfig);
        $this->runCommand('php ../bin/composer-dependency-analyser', $testsDir, 255, null, $noComposerJsonError);
        $this->runCommand('php bin/composer-dependency-analyser --help', $rootDir, 255, $helpOutput);
        $this->runCommand('php ../bin/composer-dependency-analyser --help', $testsDir, 255, $helpOutput);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.json', $rootDir, 0, $okOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.lock', $rootDir, 255, null, $noPackagesError);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=README.md', $rootDir, 255, null, $parseError);
        $this->runCommand('php ../bin/composer-dependency-analyser --composer-json=composer.json', $testsDir, 255, null, $noComposerJsonError);
        $this->runCommand('php ../bin/composer-dependency-analyser --composer-json=../composer.json --config=../composer-dependency-analyser.php', $testsDir, 0, $okOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.json --format=console', $rootDir, 0, $okOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.json --format=console --dump-usages=symfony/*', $rootDir, 1, $dumpingOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.json --format=junit', $rootDir, 0, $junitOutput, $usingConfig);
        $this->runCommand('php bin/composer-dependency-analyser --composer-json=composer.json --format=junit --dump-usages=symfony/*', $rootDir, 255, null, $junitDumpError);
    }

    private function runCommand(
        string $command,
        string $cwd,
        int $expectedExitCode,
        ?string $expectedOutputContains = null,
        ?string $expectedErrorContains = null
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

        $extraInfo = "Output was:\n" . $output . "\nError was:\n" . $errorOutput . "\n";

        $exitCode = proc_close($procHandle);
        self::assertSame(
            $expectedExitCode,
            $exitCode,
            $extraInfo
        );

        if ($expectedOutputContains !== null) {
            self::assertStringContainsString(
                $expectedOutputContains,
                $output,
                $extraInfo
            );
        }

        if ($expectedErrorContains !== null) {
            self::assertStringContainsString(
                $expectedErrorContains,
                $errorOutput,
                $extraInfo
            );
        }
    }

}
