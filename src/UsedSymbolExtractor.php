<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use function array_merge;
use function count;
use function explode;
use function is_array;
use function ltrim;
use function strlen;
use function substr;
use function token_get_all;
use const PHP_VERSION_ID;
use const T_AS;
use const T_CLASS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
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

    /**
     * When not null, we are inside class-like and use statements dont need to be parsed
     *
     * @var int|null
     */
    private $inClassLevel = null;

    /**
     * @var int
     */
    private $level = 0;

    public function __construct(string $code)
    {
        $this->tokens = token_get_all($code);
        $this->numTokens = count($this->tokens);
    }

    /**
     * As we do not verify if the resulting name are classes, it can return even used functions or constants (due to FQNs).
     * - elimination of those is solved in ComposerDependencyAnalyser::isConstOrFunction
     *
     * It does not produce any local names in current namespace
     * - this results in very limited functionality in files without namespace
     *
     * @return array<string, list<int>>
     * @license Inspired by https://github.com/doctrine/annotations/blob/2.0.0/lib/Doctrine/Common/Annotations/TokenParser.php
     */
    public function parseUsedClasses(): array
    {
        $usedSymbols = [];
        $useStatements = [];

        while ($token = $this->getNextEffectiveToken()) {
            $tokenLine = is_array($token) ? $token[2] : 0;

            if ($token[0] === T_USE) {
                $usedClass = $this->parseUseStatement();

                if ($usedClass !== null) {
                    $useStatements = array_merge($useStatements, $usedClass);
                }
            } elseif (PHP_VERSION_ID >= 80000) {
                if ($token[0] === T_NAMESPACE) {
                    $useStatements = []; // reset use statements on namespace change

                } elseif ($token[0] === T_NAME_FULLY_QUALIFIED) {
                    $symbolName = $this->normalizeBackslash($token[1]);
                    $usedSymbols[$symbolName][] = $tokenLine;

                } elseif ($token[0] === T_NAME_QUALIFIED) {
                    [$neededAlias] = explode('\\', $token[1], 2);

                    if (isset($useStatements[$neededAlias])) {
                        $symbolName = $this->normalizeBackslash($useStatements[$neededAlias] . substr($token[1], strlen($neededAlias)));
                        $usedSymbols[$symbolName][] = $tokenLine;
                    }
                } elseif ($token[0] === T_STRING) {
                    $name = $token[1];

                    if (isset($useStatements[$name])) {
                        $symbolName = $this->normalizeBackslash($useStatements[$name]);
                        $usedSymbols[$symbolName][] = $tokenLine;
                    }
                }
            } else {
                if ($token[0] === T_NAMESPACE) {
                    $this->pointer++;
                    $nextName = $this->parseNameForOldPhp();

                    if (substr($nextName, 0, 1) !== '\\') { // not a namespace-relative name, but a new namespace declaration
                        $useStatements = []; // reset use statements on namespace change
                    }
                } elseif ($token[0] === T_NS_SEPARATOR) { // fully qualified name
                    $symbolName = $this->normalizeBackslash($this->parseNameForOldPhp());

                    if ($symbolName !== '') { // e.g. \array (NS separator followed by not-a-name)
                        $usedSymbols[$symbolName][] = $tokenLine;
                    }
                } elseif ($token[0] === T_STRING) {
                    $name = $this->parseNameForOldPhp();

                    if (isset($useStatements[$name])) { // unqualified name
                        $symbolName = $this->normalizeBackslash($useStatements[$name]);
                        $usedSymbols[$symbolName][] = $tokenLine;

                    } else {
                        [$neededAlias] = explode('\\', $name, 2);

                        if (isset($useStatements[$neededAlias])) { // qualified name
                            $symbolName = $this->normalizeBackslash($useStatements[$neededAlias] . substr($name, strlen($neededAlias)));
                            $usedSymbols[$symbolName][] = $tokenLine;
                        }
                    }
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
        while ($this->pointer < $this->numTokens) {
            $token = $this->tokens[$this->pointer++];

            if (is_array($token)) {
                $tokenType = $token[0];

                if ($tokenType === T_WHITESPACE || $tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT) {
                    continue;
                }

                if ($tokenType === T_CLASS || $tokenType === T_INTERFACE || $tokenType === T_TRAIT || (PHP_VERSION_ID >= 80100 && $tokenType === T_ENUM)) {
                    $this->inClassLevel = $this->level + 1;
                }
            } elseif ($token === '{') {
                $this->level++;
            } elseif ($token === '}') {
                if ($this->level === $this->inClassLevel) {
                    $this->inClassLevel = null;
                }

                $this->level--;
            }

            return $token;
        }

        return null;
    }

    /**
     * See old behaviour: https://wiki.php.net/rfc/namespaced_names_as_token
     */
    private function parseNameForOldPhp(): string
    {
        $this->pointer--; // we already detected start token above

        $name = '';

        do {
            $token = $this->getNextEffectiveToken();
            $isNamePart = is_array($token) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR);

            if (!$isNamePart) {
                break;
            }

            $name .= $token[1];

        } while (true);

        return $name;
    }

    /**
     * @return array<string, string>|null
     */
    public function parseUseStatement(): ?array
    {
        if ($this->inClassLevel !== null) {
            return null;
        }

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
                PHP_VERSION_ID >= 80000
                && ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED)
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
