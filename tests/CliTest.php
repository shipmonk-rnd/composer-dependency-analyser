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
    public function testValidation(?string $expectedExceptionMessage, array $argv): void
    {
        if ($expectedExceptionMessage === null) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(InvalidCliException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new Cli(__DIR__, $argv);
    }

    /**
     * @return iterable<string, array{string|null, list<string>}>
     */
    public function validationDataProvider(): iterable
    {
        yield 'unknown long option' => [
            'Unknown option --unknown, see --help',
            ['bin/script.php', '--unknown']
        ];

        yield 'unknown short option' => [
            'Unknown option -u, see --help',
            ['bin/script.php', '-u']
        ];

        yield 'unknown argument' => [
            'Unknown argument unknown, see --help',
            ['bin/script.php', 'unknown']
        ];

        yield 'path passed' => [
            'Cannot pass paths (data) to analyse as arguments, use --config instead.',
            ['bin/script.php', 'data']
        ];

        yield 'valid bool options' => [
            null,
            ['bin/script.php', '--help', '--verbose', '--ignore-shadow-deps', '--ignore-unused-deps', '--ignore-dev-in-prod-deps', '--ignore-unknown-classes']
        ];

        yield 'valid options with values' => [
            null,
            ['bin/script.php', '--composer-json', '../composer.json', '--verbose']
        ];

        yield 'valid options with values, multiple' => [
            null,
            ['bin/script.php', '--composer-json', '../composer.json', '--config', '../config.php']
        ];

        yield 'valid options with values using "' => [
            null,
            ['bin/script.php', '--composer-json', '"../composer.json"', '--verbose']
        ];

        yield 'valid options with values using =' => [
            null,
            ['bin/script.php', '--composer-json=../composer.json', '--verbose']
        ];

        yield 'valid options with values using = and "' => [
            null,
            ['bin/script.php', '--composer-json="../composer.json"', '--verbose']
        ];

        yield 'missing argument for option' => [
            'Missing argument for --composer-json, see --help',
            ['bin/script.php', '--composer-json']
        ];

        yield 'missing argument for option, next option is valid' => [
            'Missing argument for --composer-json, see --help',
            ['bin/script.php', '--composer-json', '--verbose']
        ];

        yield 'missing argument for option with =' => [
            'Missing argument value in --composer-json=, see --help',
            ['bin/script.php', '--composer-json=']
        ];
    }

}
