<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\PathToScan;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidConfigException;
use ShipMonk\ComposerDependencyAnalyser\Result\ConsoleFormatter;
use ShipMonk\ComposerDependencyAnalyser\Result\JunitFormatter;
use function dirname;
use function strtr;
use const DIRECTORY_SEPARATOR;

class InitializerTest extends TestCase
{

    public function testInitConfiguration(): void
    {
        $printer = $this->createMock(Printer::class);

        $composerJson = $this->createMock(ComposerJson::class);
        $composerJson->autoloadPaths = [__DIR__ => false]; // @phpstan-ignore-line ignore readonly

        $options = new CliOptions();
        $options->ignoreUnknownClasses = true;

        $initializer = new Initializer(__DIR__, $printer, $printer);
        $config = $initializer->initConfiguration($options, $composerJson);

        self::assertEquals([new PathToScan(__DIR__, false)], $config->getPathsToScan());
        self::assertTrue($config->getIgnoreList()->shouldIgnoreUnknownClass('Any', 'any'));
        self::assertFalse($config->getIgnoreList()->shouldIgnoreError(ErrorType::SHADOW_DEPENDENCY, null, null));
    }

    public function testInitComposerJson(): void
    {
        $printer = $this->createMock(Printer::class);

        $composerJsonPath = __DIR__ . '/data/not-autoloaded/composer/sample.json';
        $cwd = dirname($composerJsonPath);

        $options = new CliOptions();
        $options->composerJson = 'sample.json';

        $initializer = new Initializer($cwd, $printer, $printer);
        $composerJson = $initializer->initComposerJson($options);

        self::assertSame(
            strtr($cwd . '/custom-vendor/autoload.php', '/', DIRECTORY_SEPARATOR),
            $composerJson->composerAutoloadPath
        );
        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );
    }

    public function testInitComposerJsonWithAbsolutePath(): void
    {
        $printer = $this->createMock(Printer::class);

        $cwd = __DIR__;
        $composerJsonPath = __DIR__ . '/data/not-autoloaded/composer/sample.json';

        $options = new CliOptions();
        $options->composerJson = $composerJsonPath;

        $initializer = new Initializer($cwd, $printer, $printer);
        $composerJson = $initializer->initComposerJson($options);

        self::assertSame(
            strtr(dirname($composerJsonPath) . '/custom-vendor/autoload.php', '/', DIRECTORY_SEPARATOR),
            $composerJson->composerAutoloadPath
        );
        self::assertSame(
            [
                'nette/utils' => false,
                'phpstan/phpstan' => true,
            ],
            $composerJson->dependencies
        );
    }

    public function testInitCliOptions(): void
    {
        $printer = $this->createMock(Printer::class);

        $initializer = new Initializer(__DIR__, $printer, $printer);
        $options = $initializer->initCliOptions(__DIR__, ['script.php', '--verbose']);

        self::assertNull($options->showAllUsages);
        self::assertNull($options->composerJson);
        self::assertNull($options->ignoreProdOnlyInDevDeps);
        self::assertNull($options->ignoreUnknownClasses);
        self::assertNull($options->ignoreUnknownFunctions);
        self::assertNull($options->ignoreUnusedDeps);
        self::assertNull($options->ignoreShadowDeps);
        self::assertNull($options->ignoreDevInProdDeps);
        self::assertTrue($options->verbose);
    }

    public function testInitCliOptionsHelp(): void
    {
        $printer = $this->createMock(Printer::class);

        $initializer = new Initializer(__DIR__, $printer, $printer);

        $this->expectException(InvalidCliException::class);
        $initializer->initCliOptions(__DIR__, ['script.php', '--help']);
    }

    public function testInitFormatter(): void
    {
        $printer = $this->createMock(Printer::class);

        $initializer = new Initializer(__DIR__, $printer, $printer);

        $optionsNoFormat = new CliOptions();
        self::assertInstanceOf(ConsoleFormatter::class, $initializer->initFormatter($optionsNoFormat));

        $optionsFormatConsole = new CliOptions();
        $optionsFormatConsole->format = 'console';
        self::assertInstanceOf(ConsoleFormatter::class, $initializer->initFormatter($optionsFormatConsole));

        $optionsFormatJunit = new CliOptions();
        $optionsFormatJunit->format = 'junit';
        self::assertInstanceOf(JunitFormatter::class, $initializer->initFormatter($optionsFormatJunit));

        self::expectException(InvalidConfigException::class);
        $optionsFormatUnknown = new CliOptions();
        $optionsFormatUnknown->format = 'unknown';
        $initializer->initFormatter($optionsFormatUnknown);
    }

}
