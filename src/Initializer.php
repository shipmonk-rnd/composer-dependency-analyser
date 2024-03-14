<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use Composer\Autoload\ClassLoader;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidConfigException;
use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use Throwable;
use function count;
use function get_class;
use function is_file;

class Initializer
{

    /**
     * @var string
     */
    private static $help = <<<'EOD'

Usage:
    vendor/bin/composer-analyser

Options:
    --help                      Print this help text and exit.
    --verbose                   Print more usage examples
    --show-all-usages           Removes the limit of showing only few usages
    --dump-usages <package>     Dump usages of given package, * placeholder can be used
    --composer-json <path>      Provide custom path to composer.json
    --config <path>             Provide path to php configuration file
                                (must return \ShipMonk\ComposerDependencyAnalyser\Config\Configuration instance)

Ignore options:
    (or use --config for better granularity)

    --ignore-unknown-classes            Ignore when class is not found in classmap
    --ignore-unused-deps                Ignore all unused dependency issues
    --ignore-shadow-deps                Ignore all shadow dependency issues
    --ignore-dev-in-prod-deps           Ignore all dev dependency in production code issues
    --ignore-prod-only-in-dev-deps      Ignore all prod dependency used only in dev paths issues


EOD;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var Printer
     */
    private $printer;

    public function __construct(
        string $cwd,
        Printer $printer
    )
    {
        $this->printer = $printer;
        $this->cwd = $cwd;
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidPathException
     */
    public function initConfiguration(
        CliOptions $options,
        ComposerJson $composerJson
    ): Configuration
    {
        if ($options->config !== null) {
            $configPath = $this->cwd . '/' . $options->config;

            if (!is_file($configPath)) {
                throw new InvalidConfigException("Invalid config path given, {$configPath} is not a file.");
            }
        } else {
            $configPath = $this->cwd . '/composer-dependency-analyser.php';
        }

        if (is_file($configPath)) {
            $this->printer->printLine('<gray>Using config</gray> ' . $configPath);

            try {
                $config = require $configPath;
            } catch (Throwable $e) {
                throw new InvalidConfigException(get_class($e) . " in {$e->getFile()}:{$e->getLine()}\n > " . $e->getMessage(), 0, $e);
            }

            if (!$config instanceof Configuration) {
                throw new InvalidConfigException('Invalid config file, it must return instance of ' . Configuration::class);
            }
        } else {
            $config = new Configuration();
        }

        $ignoreUnknown = $options->ignoreUnknownClasses === true;
        $ignoreUnused = $options->ignoreUnusedDeps === true;
        $ignoreShadow = $options->ignoreShadowDeps === true;
        $ignoreDevInProd = $options->ignoreDevInProdDeps === true;
        $ignoreProdOnlyInDev = $options->ignoreProdOnlyInDevDeps === true;

        if ($ignoreUnknown) {
            $config->ignoreErrors([ErrorType::UNKNOWN_CLASS]);
        }

        if ($ignoreUnused) {
            $config->ignoreErrors([ErrorType::UNUSED_DEPENDENCY]);
        }

        if ($ignoreShadow) {
            $config->ignoreErrors([ErrorType::SHADOW_DEPENDENCY]);
        }

        if ($ignoreDevInProd) {
            $config->ignoreErrors([ErrorType::DEV_DEPENDENCY_IN_PROD]);
        }

        if ($ignoreProdOnlyInDev) {
            $config->ignoreErrors([ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
        }

        if ($config->shouldScanComposerAutoloadPaths()) {
            foreach ($composerJson->autoloadPaths as $absolutePath => $isDevPath) {
                $config->addPathToScan($absolutePath, $isDevPath);
            }

            if ($config->getPathsToScan() === []) {
                throw new InvalidConfigException('No paths to scan! There is no composer autoload section and no extra path to scan configured.');
            }
        } else {
            if ($config->getPathsToScan() === []) {
                throw new InvalidConfigException('No paths to scan! Scanning composer\'s \'autoload\' sections is disabled and no extra path to scan was configured.');
            }
        }

        return $config;
    }

    /**
     * @throws InvalidPathException
     * @throws InvalidConfigException
     */
    public function initComposerJson(string $cwd, CliOptions $options): ComposerJson
    {
        $composerJsonPath = $options->composerJson !== null
            ? ($cwd . '/' . $options->composerJson)
            : ($cwd . '/composer.json');

        return new ComposerJson($composerJsonPath);
    }

    /**
     * @throws InvalidConfigException
     */
    public function initComposerAutoloader(ComposerJson $composerJson): void
    {
        // load vendor that belongs to given composer.json
        $autoloadFile = $composerJson->composerAutoloadPath;

        if (!is_file($autoloadFile)) {
            throw new InvalidConfigException("Cannot find composer's autoload file, expected at '$autoloadFile'");
        }

        require $autoloadFile;
    }

    /**
     * @return array<string, ClassLoader>
     */
    public function initComposerClassLoaders(): array
    {
        $loaders = ClassLoader::getRegisteredLoaders();

        if (count($loaders) > 1) {
            $this->printer->printLine("\nDetected multiple class loaders:");

            foreach ($loaders as $vendorDir => $_) {
                $this->printer->printLine(" • <gray>$vendorDir</gray>");
            }

            $this->printer->printLine('');
        }

        if (count($loaders) === 0) {
            $this->printer->printLine("\nNo composer class loader detected!\n");
        }

        return $loaders;
    }

    /**
     * @param list<string> $argv
     * @throws InvalidCliException
     */
    public function initCliOptions(string $cwd, array $argv): CliOptions
    {
        $cliOptions = (new Cli($cwd, $argv))->getProvidedOptions();

        if ($cliOptions->help !== null) {
            $this->printer->printLine(self::$help);
            throw new InvalidCliException(''); // just exit
        }

        return $cliOptions;
    }

}
