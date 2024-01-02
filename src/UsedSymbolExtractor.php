<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use function array_merge;
use function count;
use function explode;
use function ltrim;
use function strlen;
use function substr;
use function token_get_all;
use const T_AS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;

class UsedSymbolExtractor
{

    /**
     * @var list<array{int, string, int}|string>
     */
    private $tokens;

    /**
     * @var int
     */
    private $numTokens;

    /**
     * @var int
     */
    private $pointer = 0;

    public function __construct(string $code)
    {
        $this->tokens = token_get_all($code);
        $this->numTokens = count($this->tokens);
    }

    /**
     * As we do not verify if the resulting name are classes, it can return even used functions or constants (due to FQNs).
     * - elimination of those is solved in ComposerDependencyAnalyser::isClass
     *
     * @return list<string>
     */
    public function parseUsedClasses(): array
    {
        $usedSymbols = [];
        $useStatements = [];

        while ($token = $this->getNextEffectiveToken()) {
            if ($token[0] === T_NAMESPACE) {
                $useStatements = []; // reset use statements on namespace change
            }

            if ($token[0] === T_USE) {
                $usedClass = $this->parseUseStatement();

                if ($usedClass !== null) {
                    $useStatements = array_merge($useStatements, $usedClass);
                }
            }

            if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                $usedSymbols[] = $this->normalizeBackslash($token[1]);
            }

            if ($token[0] === T_NAME_QUALIFIED) {
                [$neededAlias] = explode('\\', $token[1], 2);

                if (isset($useStatements[$neededAlias])) {
                    $usedSymbols[] = $this->normalizeBackslash($useStatements[$neededAlias] . substr($token[1], strlen($neededAlias)));
                }
            }

            if ($token[0] === T_STRING) {
                $symbolName = $token[1];

                if (isset($useStatements[$symbolName])) {
                    $usedSymbols[] = $this->normalizeBackslash($useStatements[$symbolName]);
                }
            }
        }

        return $usedSymbols;
    }

    /**
     * @return array{int, string, int}|string|null The token if exists, null otherwise.
     */
    private function getNextEffectiveToken()
    {
        for ($i = $this->pointer; $i < $this->numTokens; $i++) {
            $this->pointer++;
            $token = $this->tokens[$i];

            if (
                $token[0] === T_WHITESPACE ||
                $token[0] === T_COMMENT ||
                $token[0] === T_DOC_COMMENT
            ) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    public function parseUseStatement(): ?array
    {
        $groupRoot = '';
        $class = '';
        $alias = '';
        $statements = [];
        $explicitAlias = false;

        while (($token = $this->getNextEffectiveToken())) {
            if (!$explicitAlias && $token[0] === T_STRING) {
                $class .= $token[1];
                $alias = $token[1];
            } elseif ($explicitAlias && $token[0] === T_STRING) {
                $alias = $token[1];
            } elseif (
                $token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED
            ) {
                $class .= $token[1];

                $classSplit = explode('\\', $token[1]);
                $alias = $classSplit[count($classSplit) - 1];
            } elseif ($token[0] === T_NS_SEPARATOR) {
                $class .= '\\';
                $alias = '';
            } elseif ($token[0] === T_AS) {
                $explicitAlias = true;
                $alias = '';
            } elseif ($token === ',') {
                $statements[$alias] = $groupRoot . $class;
                $class = '';
                $alias = '';
                $explicitAlias = false;
            } elseif ($token === ';') {
                $statements[$alias] = $groupRoot . $class;
                break;
            } elseif ($token === '{') {
                $groupRoot = $class;
                $class = '';
            } elseif ($token === '}') {
                continue;
            } else {
                break;
            }
        }

        return $statements === [] ? null : $statements;
    }

    private function normalizeBackslash(string $class): string
    {
        return ltrim($class, '\\');
    }

}
