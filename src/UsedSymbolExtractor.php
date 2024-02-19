<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

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
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
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

        $level = 0;
        $inClassLevel = null;

        $numTokens = $this->numTokens;
        $tokens = $this->tokens;

        while ($this->pointer < $numTokens) {
            $token = $tokens[$this->pointer++];

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CLASS:
                    case T_INTERFACE:
                    case T_TRAIT:
                    case PHP_VERSION_ID >= 80100 ? T_ENUM : -1:
                        $inClassLevel = $level + 1;
                        break;

                    case T_USE:
                        if ($inClassLevel === null) {
                            foreach ($this->parseUseStatement() as $alias => $class) {
                                $useStatements[$alias] = $this->normalizeBackslash($class);
                            }
                        }

                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAMESPACE : -1:
                        $useStatements = []; // reset use statements on namespace change
                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAME_FULLY_QUALIFIED : -1:
                        $symbolName = $this->normalizeBackslash($token[1]);
                        $usedSymbols[$symbolName][] = $token[2];
                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAME_QUALIFIED : -1:
                        [$neededAlias] = explode('\\', $token[1], 2);

                        if (isset($useStatements[$neededAlias])) {
                            $symbolName = $useStatements[$neededAlias] . substr($token[1], strlen($neededAlias));
                            $usedSymbols[$symbolName][] = $token[2];
                        }

                        break;

                    case PHP_VERSION_ID >= 80000 ? T_STRING : -1:
                        $name = $token[1];

                        if (isset($useStatements[$name])) {
                            $symbolName = $useStatements[$name];
                            $usedSymbols[$symbolName][] = $token[2];
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_NAMESPACE : -1:
                        $this->pointer++;
                        $nextName = $this->parseNameForOldPhp();

                        if (substr($nextName, 0, 1) !== '\\') { // not a namespace-relative name, but a new namespace declaration
                            $useStatements = []; // reset use statements on namespace change
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_NS_SEPARATOR : -1:
                        $symbolName = $this->normalizeBackslash($this->parseNameForOldPhp());

                        if ($symbolName !== '') { // e.g. \array (NS separator followed by not-a-name)
                            $usedSymbols[$symbolName][] = $token[2];
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_STRING : -1:
                        $name = $this->parseNameForOldPhp();

                        if (isset($useStatements[$name])) { // unqualified name
                            $symbolName = $useStatements[$name];
                            $usedSymbols[$symbolName][] = $token[2];

                        } else {
                            [$neededAlias] = explode('\\', $name, 2);

                            if (isset($useStatements[$neededAlias])) { // qualified name
                                $symbolName = $useStatements[$neededAlias] . substr($name, strlen($neededAlias));
                                $usedSymbols[$symbolName][] = $token[2];
                            }
                        }

                        break;

                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                        $level++;
                        break;
                }
            } elseif ($token === '{') {
                $level++;
            } elseif ($token === '}') {
                if ($level === $inClassLevel) {
                    $inClassLevel = null;
                }

                $level--;
            }
        }

        return $usedSymbols;
    }

    /**
     * See old behaviour: https://wiki.php.net/rfc/namespaced_names_as_token
     */
    private function parseNameForOldPhp(): string
    {
        $this->pointer--; // we already detected start token above
        $name = '';

        while ($this->pointer < $this->numTokens) {
            $token = $this->tokens[$this->pointer++];

            if (!is_array($token) || ($token[0] !== T_STRING && $token[0] !== T_NS_SEPARATOR)) {
                break;
            }

            $name .= $token[1];
        }

        return $name;
    }

    /**
     * @return array<string, string>
     */
    public function parseUseStatement(): array
    {
        $groupRoot = '';
        $class = '';
        $alias = '';
        $statements = [];
        $explicitAlias = false;

        while ($this->pointer < $this->numTokens) {
            $token = $this->tokens[$this->pointer++];

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_STRING:
                        $alias = $token[1];

                        if (!$explicitAlias) {
                            $class .= $alias;
                        }

                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAME_QUALIFIED : -1:
                    case PHP_VERSION_ID >= 80000 ? T_NAME_FULLY_QUALIFIED : -1:
                        $class .= $token[1];
                        $classSplit = explode('\\', $token[1]);
                        $alias = $classSplit[count($classSplit) - 1];
                        break;

                    case T_NS_SEPARATOR:
                        $class .= '\\';
                        $alias = '';
                        break;

                    case T_AS:
                        $explicitAlias = true;
                        $alias = '';
                        break;

                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;

                    default:
                        break 2;
                }
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

        return $statements;
    }

    private function normalizeBackslash(string $class): string
    {
        return ltrim($class, '\\');
    }

}
