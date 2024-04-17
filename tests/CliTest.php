<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;

class CliTest extends TestCase
{

    /**
     * @param list<string> $argv
     * @dataProvider validationDataProvider
     */
    public function testValidation(?string $expectedExceptionMessage, array $argv, ?CliOptions $options = null): void
    {
        if ($expectedExceptionMessage !== null) {
            $this->expectException(InvalidCliException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $cli = new Cli(__DIR__, $argv);

        if ($options !== null) {
            self::assertEquals($options, $cli->getProvidedOptions());
        }
    }

    /**
     * @return iterable<string, array{string|null, list<string>}>
     */
    public function validationDataProvider(): iterable
    {
        yield 'unknown long option' => [
            'Unknown option --unknown, see --help',
            ['bin/script.php', '--unknown'],
        ];

        yield 'unknown short option' => [
            'Unknown option -u, see --help',
            ['bin/script.php', '-u'],
        ];

        yield 'unknown argument' => [
            'Unknown argument unknown, see --help',
            ['bin/script.php', 'unknown'],
        ];

        yield 'path passed' => [
            'Cannot pass paths (data) to analyse as arguments, use --config instead.',
            ['bin/script.php', 'data'],
        ];

        yield 'valid bool options' => [
            null,
            ['bin/script.php', '--help', '--verbose', '--ignore-shadow-deps', '--ignore-unused-deps', '--ignore-dev-in-prod-deps', '--ignore-unknown-classes', '--ignore-unknown-functions'],
            (static function (): CliOptions {
                $options = new CliOptions();
                $options->help = true;
                $options->verbose = true;
                $options->ignoreShadowDeps = true;
                $options->ignoreUnusedDeps = true;
                $options->ignoreDevInProdDeps = true;
                $options->ignoreUnknownClasses = true;
                $options->ignoreUnknownFunctions = true;
                return $options;
            })(),
        ];

        yield 'valid options with values' => [
            null,
            ['bin/script.php', '--composer-json', '../composer.json', '--verbose'],
            (static function (): CliOptions {
                $options = new CliOptions();
                $options->composerJson = '../composer.json';
                $options->verbose = true;
                return $options;
            })(),
        ];

        yield 'valid options with values, multiple' => [
            null,
            ['bin/script.php', '--composer-json', '../composer.json', '--config', '../config.php'],
            (static function (): CliOptions {
                $options = new CliOptions();
                $options->composerJson = '../composer.json';
                $options->config = '../config.php';
                return $options;
            })(),
        ];

        yield 'valid options with values using =' => [
            null,
            ['bin/script.php', '--composer-json=../composer.json', '--verbose'],
            (static function (): CliOptions {
                $options = new CliOptions();
                $options->composerJson = '../composer.json';
                $options->verbose = true;
                return $options;
            })(),
        ];

        yield 'missing argument for option' => [
            'Missing argument for --composer-json, see --help',
            ['bin/script.php', '--composer-json'],
        ];

        yield 'missing argument for option, next option is valid' => [
            'Missing argument for --composer-json, see --help',
            ['bin/script.php', '--composer-json', '--verbose'],
        ];

        yield 'missing argument for option with =' => [
            'Missing argument value in --composer-json=, see --help',
            ['bin/script.php', '--composer-json='],
        ];
    }

}
