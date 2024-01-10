<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidCliException;
use function array_slice;
use function getopt;
use function is_dir;
use function is_file;
use function next;
use function rtrim;
use function strlen;
use function strpos;
use function substr;

class Cli
{

    private const OPTIONS = [
        'help',
        'verbose',
        'ignore-shadow-deps',
        'ignore-unused-deps',
        'ignore-dev-in-prod-deps',
        'ignore-prod-only-in-dev-deps',
        'ignore-unknown-classes',
        'composer-json:',
        'config:'
    ];

    /**
     * @param list<string> $argv
     * @throws InvalidCliException
     */
    public function validateArgv(string $cwd, array $argv): void
    {
        $ignoreNextArg = false;
        $argsWithoutScript = array_slice($argv, 1);

        foreach ($argsWithoutScript as $arg) {
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
            $optionIndex = $this->getKnownOptionIndex($noDashesArg);

            if ($optionIndex === null) {
                throw new InvalidCliException("Unknown option $arg, see --help");
            }

            if ($this->isOptionWithRequiredValue($optionIndex)) {
                $optionArgument = $this->getOptionArgumentAfterAssign($arg);

                if ($optionArgument === null) { // next $arg is the argument
                    $ignoreNextArg = true;
                    $nextArg = next($argsWithoutScript);

                    if ($nextArg === false || strpos($nextArg, '-') === 0) {
                        throw new InvalidCliException("Missing argument for $arg, see --help");
                    }
                } elseif ($optionArgument === '') {
                    throw new InvalidCliException("Missing argument value in $arg, see --help");
                }
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

    private function isOptionWithRequiredValue(int $optionIndex): bool
    {
        return strpos(self::OPTIONS[$optionIndex], ':') === strlen(self::OPTIONS[$optionIndex]) - 1;
    }

    private function getKnownOptionIndex(string $option): ?int
    {
        foreach (self::OPTIONS as $index => $knownOption) {
            $knownOptionNoColon = rtrim($knownOption, ':');

            if (strpos($option, $knownOptionNoColon) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     help?: bool,
     *     verbose?: bool,
     *     ignore-shadow-deps?: bool,
     *     ignore-unused-deps?: bool,
     *     ignore-dev-in-prod-deps?: bool,
     *     ignore-prod-only-in-dev-deps?: bool,
     *     ignore-unknown-classes?: bool,
     *     composer-json?: string,
     *     config?: string
     * }
     */
    public function getProvidedOptions(): array
    {
        return getopt('', self::OPTIONS); // @phpstan-ignore-line assume validation was performed anc $argv is the real one
    }

}
