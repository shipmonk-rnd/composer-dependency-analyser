<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;
use function array_slice;
use function is_dir;
use function is_file;
use function strpos;
use function substr;

class Cli
{

    private const OPTIONS = [
        'version' => false,
        'help' => false,
        'verbose' => false,
        'ignore-shadow-deps' => false,
        'ignore-unused-deps' => false,
        'ignore-dev-in-prod-deps' => false,
        'ignore-prod-only-in-dev-deps' => false,
        'ignore-unknown-classes' => false,
        'ignore-unknown-functions' => false,
        'ignore-unknown-symbols' => false,
        'composer-json' => true,
        'config' => true,
        'dump-usages' => true,
        'show-all-usages' => false,
        'format' => true,
    ];

    /**
     * @var array<string, bool|string>
     */
    private $providedOptions = [];

    /**
     * @param list<string> $argv
     * @throws InvalidCliException
     */
    public function __construct(string $cwd, array $argv)
    {
        $ignoreNextArg = false;
        $argsWithoutScript = array_slice($argv, 1);

        foreach ($argsWithoutScript as $argIndex => $arg) {
            if ($ignoreNextArg === true) {
                $ignoreNextArg = false;
                continue;
            }

            $startsWithDash = strpos($arg, '-') === 0;
            $startsWithDashDash = strpos($arg, '--') === 0;

            if ($startsWithDash && !$startsWithDashDash) {
                throw new InvalidCliException("Unknown option $arg, see --help");
            }

            if (!$startsWithDashDash) {
                if (is_file($cwd . '/' . $arg) || is_dir($cwd . '/' . $arg)) {
                    throw new InvalidCliException("Cannot pass paths ($arg) to analyse as arguments, use --config instead.");
                }

                throw new InvalidCliException("Unknown argument $arg, see --help");
            }

            /** @var string $noDashesArg this is never false as we know it starts with -- */
            $noDashesArg = substr($arg, 2);
            $optionName = $this->getKnownOptionName($noDashesArg);

            if ($optionName === null) {
                throw new InvalidCliException("Unknown option $arg, see --help");
            }

            if ($this->isOptionWithRequiredValue($optionName)) {
                $optionArgument = $this->getOptionArgumentAfterAssign($arg);

                if ($optionArgument === null) { // next $arg is the argument
                    $ignoreNextArg = true;
                    $nextArg = $argsWithoutScript[$argIndex + 1] ?? false;

                    if ($nextArg === false || strpos($nextArg, '-') === 0) {
                        throw new InvalidCliException("Missing argument for $arg, see --help");
                    }

                    $this->providedOptions[$optionName] = $nextArg;
                } elseif ($optionArgument === '') {
                    throw new InvalidCliException("Missing argument value in $arg, see --help");
                } else {
                    $this->providedOptions[$optionName] = $optionArgument;
                }
            } else {
                $this->providedOptions[$optionName] = true;
            }
        }
    }

    private function getOptionArgumentAfterAssign(string $arg): ?string
    {
        $position = strpos($arg, '=');

        if ($position !== false) {
            return substr($arg, $position + 1); // @phpstan-ignore-line this will never be false
        }

        return null;
    }

    private function isOptionWithRequiredValue(string $optionName): bool
    {
        return self::OPTIONS[$optionName];
    }

    private function getKnownOptionName(string $option): ?string
    {
        foreach (self::OPTIONS as $knownOption => $needsArgument) {
            if (strpos($option, $knownOption) === 0) {
                return $knownOption;
            }
        }

        return null;
    }

    public function getProvidedOptions(): CliOptions
    {
        $options = new CliOptions();

        if (isset($this->providedOptions['version'])) {
            $options->version = true;
        }

        if (isset($this->providedOptions['help'])) {
            $options->help = true;
        }

        if (isset($this->providedOptions['verbose'])) {
            $options->verbose = true;
        }

        if (isset($this->providedOptions['ignore-shadow-deps'])) {
            $options->ignoreShadowDeps = true;
        }

        if (isset($this->providedOptions['ignore-unused-deps'])) {
            $options->ignoreUnusedDeps = true;
        }

        if (isset($this->providedOptions['ignore-dev-in-prod-deps'])) {
            $options->ignoreDevInProdDeps = true;
        }

        if (isset($this->providedOptions['ignore-prod-only-in-dev-deps'])) {
            $options->ignoreProdOnlyInDevDeps = true;
        }

        if (isset($this->providedOptions['ignore-unknown-classes'])) {
            $options->ignoreUnknownClasses = true;
        }

        if (isset($this->providedOptions['ignore-unknown-functions'])) {
            $options->ignoreUnknownFunctions = true;
        }

        if (isset($this->providedOptions['composer-json'])) {
            $options->composerJson = $this->providedOptions['composer-json']; // @phpstan-ignore-line type is ensured
        }

        if (isset($this->providedOptions['config'])) {
            $options->config = $this->providedOptions['config']; // @phpstan-ignore-line type is ensured
        }

        if (isset($this->providedOptions['dump-usages'])) {
            $options->dumpUsages = $this->providedOptions['dump-usages'];  // @phpstan-ignore-line type is ensured
        }

        if (isset($this->providedOptions['show-all-usages'])) {
            $options->showAllUsages = true;
        }

        if (isset($this->providedOptions['format'])) {
            $options->format = $this->providedOptions['format']; // @phpstan-ignore-line type is ensured
        }

        return $options;
    }

}
