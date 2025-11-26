<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PHPUnit\Framework\TestCase;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;

class CliTest extends TestCase
{

    /**
     * @param list<string> $argv
     *
     * @dataProvider validationDataProvider
     */
    public function testValidation(
        ?string $expectedExceptionMessage,
        array $argv,
        ?CliOptions $options = null
    ): void
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
            ['bin/script.php', '--help', '--verbose', '--ignore-shadow-deps', '--ignore-unused-deps', '--ignore-dev-in-prod-deps', '--ignore-unknown-classes', '--ignore-unknown-functions', '--disable-ext-analysis'],
            (static function (): CliOptions {
                $options = new CliOptions();
                $options->help = true;
                $options->verbose = true;
                $options->disableExtAnalysis = true;
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

        yield 'valid option is substring of provided option' => [
            'Unknown option --configuration=foo, see --help',
            ['bin/script.php', '--configuration=foo'],
        ];

        yield 'argument-less option with argument' => [
            'Option --verbose does not accept arguments, see --help',
            ['bin/script.php', '--verbose=foo'],
        ];

        yield 'suggestion #1' => [
            'Unknown option --hep, did you mean --help?',
            ['bin/script.php', '--hep'],
        ];

        yield 'suggestion #2' => [
            'Unknown option --ignore-shadow-dependencies, did you mean --ignore-shadow-deps?',
            ['bin/script.php', '--ignore-shadow-dependencies'],
        ];

        yield 'suggestion #3' => [
            'Unknown option --composer-lock, did you mean --composer-json?',
            ['bin/script.php', '--composer-lock'],
        ];

        yield 'suggestion #4' => [
            'Unknown option --ignore-prod-in-dev-deps, did you mean --ignore-prod-only-in-dev-deps?',
            ['bin/script.php', '--ignore-prod-in-dev-deps'],
        ];

        yield 'suggestion #5' => [
            'Unknown option --ignore-dev-prod-deps, did you mean --ignore-dev-in-prod-deps?',
            ['bin/script.php', '--ignore-dev-prod-deps'],
        ];

        yield 'no suggestion #1' => [
            'Unknown option --vvv, see --help',
            ['bin/script.php', '--vvv'],
        ];

        yield 'no suggestion #2' => [
            'Unknown option --v, see --help',
            ['bin/script.php', '--v'],
        ];

        yield 'no suggestion #3' => [
            'Unknown option --nonsense, see --help',
            ['bin/script.php', '--nonsense'],
        ];

        yield 'no suggestion #4' => [
            'Unknown option --four, see --help',
            ['bin/script.php', '--four'],
        ];
    }

}
