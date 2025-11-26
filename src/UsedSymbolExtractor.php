<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use function count;
use function explode;
use function is_array;
use function ltrim;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function token_get_all;
use const PHP_VERSION_ID;
use const T_AS;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOUBLE_COLON;
use const T_ENUM;
use const T_FUNCTION;
use const T_INSTEADOF;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

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
     * It does not produce any local names in current namespace
     * - this results in very limited functionality in files without namespace
     *
     * @param array<string, SymbolKind::*> $knownSymbols
     * @return array<SymbolKind::*, array<string, list<int>>>
     *
     * @license Inspired by https://github.com/doctrine/annotations/blob/2.0.0/lib/Doctrine/Common/Annotations/TokenParser.php
     */
    public function parseUsedSymbols(
        array $knownSymbols
    ): array
    {
        $usedSymbols = [];
        $useStatements = [];
        $useStatementKinds = [];

        $level = 0; // {, }, {$, ${
        $squareLevel = 0; // [, ], #[
        $inGlobalScope = true;
        $inClassLevel = null;
        $inAttributeSquareLevel = null;

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
                            foreach ($this->parseUseStatement() as $alias => [$fullSymbolName, $symbolKind]) {
                                $useStatements[$alias] = $this->normalizeBackslash($fullSymbolName);
                                $useStatementKinds[$alias] = $symbolKind;
                            }
                        }

                        break;

                    case PHP_VERSION_ID > 80000 ? T_ATTRIBUTE : -1:
                        $inAttributeSquareLevel = ++$squareLevel;
                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAMESPACE : -1:
                        // namespace change
                        $inGlobalScope = false;
                        $useStatements = [];
                        $useStatementKinds = [];
                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAME_FULLY_QUALIFIED : -1:
                        $symbolName = $this->normalizeBackslash($token[1]);
                        $lowerSymbolName = strtolower($symbolName);
                        $kind = $knownSymbols[$lowerSymbolName] ?? $this->getFqnSymbolKind($this->pointer - 2, $this->pointer, $inAttributeSquareLevel !== null);
                        $usedSymbols[$kind][$symbolName][] = $token[2];
                        break;

                    case PHP_VERSION_ID >= 80000 ? T_NAME_QUALIFIED : -1:
                        [$neededAlias] = explode('\\', $token[1], 2);

                        if (isset($useStatements[$neededAlias])) {
                            $symbolName = $useStatements[$neededAlias] . substr($token[1], strlen($neededAlias));
                        } elseif ($inGlobalScope) {
                            $symbolName = $token[1];
                        } else {
                            break;
                        }

                        $lowerSymbolName = strtolower($symbolName);
                        $kind = $knownSymbols[$lowerSymbolName] ?? $this->getFqnSymbolKind($this->pointer - 2, $this->pointer, $inAttributeSquareLevel !== null);
                        $usedSymbols[$kind][$symbolName][] = $token[2];

                        break;

                    case PHP_VERSION_ID >= 80000 ? T_STRING : -1:
                        $name = $token[1];
                        $lowerName = strtolower($name);
                        $pointerBeforeName = $this->pointer - 2;
                        $pointerAfterName = $this->pointer;

                        if (!$this->canBeSymbolName($pointerBeforeName, $pointerAfterName)) {
                            break;
                        }

                        if (isset($useStatements[$name])) {
                            $symbolName = $useStatements[$name];
                            $kind = $useStatementKinds[$name];
                            $usedSymbols[$kind][$symbolName][] = $token[2];

                        } elseif (isset($knownSymbols[$lowerName])) {
                            $symbolName = $name;
                            $kind = $knownSymbols[$lowerName];

                            if (!$inGlobalScope && $kind === SymbolKind::CLASSLIKE) {
                                break; // cannot use class-like symbols in non-global scope when not imported
                            }

                            $usedSymbols[$kind][$symbolName][] = $token[2];

                        } elseif (
                            $inGlobalScope
                            && $this->getTokenAfter($pointerAfterName)[0] === T_DOUBLE_COLON
                        ) {
                            // unqualified static access (e.g., Foo::class, Foo::method(), Foo::CONSTANT) in global scope
                            // register to allow detection of classes not in $knownSymbols
                            $usedSymbols[SymbolKind::CLASSLIKE][$name][] = $token[2];
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_NAMESPACE : -1:
                        $this->pointer++;
                        $nextName = $this->parseNameForOldPhp();

                        if (substr($nextName, 0, 1) !== '\\') { // not a namespace-relative name, but a new namespace declaration
                            // namespace change
                            $inGlobalScope = false;
                            $useStatements = [];
                            $useStatementKinds = [];
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_NS_SEPARATOR : -1:
                        $pointerBeforeName = $this->pointer - 2;
                        $symbolName = $this->normalizeBackslash($this->parseNameForOldPhp());
                        $lowerSymbolName = strtolower($symbolName);

                        if ($symbolName !== '') { // e.g. \array (NS separator followed by not-a-name)
                            $kind = $knownSymbols[$lowerSymbolName] ?? $this->getFqnSymbolKind($pointerBeforeName, $this->pointer - 1, false);
                            $usedSymbols[$kind][$symbolName][] = $token[2];
                        }

                        break;

                    case PHP_VERSION_ID < 80000 ? T_STRING : -1:
                        $pointerBeforeName = $this->pointer - 2;
                        $name = $this->parseNameForOldPhp();
                        $lowerName = strtolower($name);
                        $pointerAfterName = $this->pointer - 1;

                        if (!$this->canBeSymbolName($pointerBeforeName, $pointerAfterName)) {
                            break;
                        }

                        if (isset($useStatements[$name])) { // unqualified name
                            $symbolName = $useStatements[$name];
                            $kind = $useStatementKinds[$name];
                            $usedSymbols[$kind][$symbolName][] = $token[2];

                        } elseif (isset($knownSymbols[$lowerName])) {
                            $symbolName = $name;
                            $kind = $knownSymbols[$lowerName];

                            if (!$inGlobalScope && $kind === SymbolKind::CLASSLIKE) {
                                break; // cannot use class-like symbols in non-global scope when not imported
                            }

                            $usedSymbols[$kind][$symbolName][] = $token[2];

                        } else {
                            [$neededAlias] = explode('\\', $name, 2);

                            if (isset($useStatements[$neededAlias])) { // qualified name
                                $symbolName = $useStatements[$neededAlias] . substr($name, strlen($neededAlias));
                                $kind = $this->getFqnSymbolKind($pointerBeforeName, $pointerAfterName, false);
                                $usedSymbols[$kind][$symbolName][] = $token[2];

                            } elseif ($inGlobalScope && strpos($name, '\\') !== false) {
                                $symbolName = $name;
                                $kind = $this->getFqnSymbolKind($pointerBeforeName, $pointerAfterName, false);
                                $usedSymbols[$kind][$symbolName][] = $token[2];

                            } elseif (
                                strpos($name, '\\') === false
                                && $inGlobalScope
                                && $this->getTokenAfter($pointerAfterName)[0] === T_DOUBLE_COLON
                            ) {
                                // unqualified static access (e.g., Foo::class, Foo::method(), Foo::CONSTANT) in global scope
                                // register to allow detection of classes not in $knownSymbols
                                $usedSymbols[SymbolKind::CLASSLIKE][$name][] = $token[2];
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
            } elseif ($token === '[') {
                $squareLevel++;
            } elseif ($token === ']') {
                if ($squareLevel === $inAttributeSquareLevel) {
                    $inAttributeSquareLevel = null;
                }

                $squareLevel--;
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
     * @return array<string, array{string, SymbolKind::*}>
     */
    private function parseUseStatement(): array
    {
        $groupRoot = '';
        $class = '';
        $alias = '';
        $statements = [];
        $kind = SymbolKind::CLASSLIKE;
        $explicitAlias = false;
        $kindFrozen = false;

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

                    case T_FUNCTION:
                        $kind = SymbolKind::FUNCTION;
                        break;

                    case T_CONST:
                        $kind = SymbolKind::CONSTANT;
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
                $statements[$alias] = [$groupRoot . $class, $kind];

                if (!$kindFrozen) {
                    $kind = SymbolKind::CLASSLIKE;
                }

                $class = '';
                $alias = '';
                $explicitAlias = false;
            } elseif ($token === ';') {
                $statements[$alias] = [$groupRoot . $class, $kind];

                break;
            } elseif ($token === '{') {
                $kindFrozen = ($kind === SymbolKind::FUNCTION || $kind === SymbolKind::CONSTANT);
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

    /**
     * @return SymbolKind::CLASSLIKE|SymbolKind::FUNCTION
     */
    private function getFqnSymbolKind(
        int $pointerBeforeName,
        int $pointerAfterName,
        bool $inAttribute
    ): int
    {
        if ($inAttribute) {
            return SymbolKind::CLASSLIKE;
        }

        $tokenBeforeName = $this->getTokenBefore($pointerBeforeName);
        $tokenAfterName = $this->getTokenAfter($pointerAfterName);

        if (
            $tokenAfterName === '('
            && $tokenBeforeName[0] !== T_NEW // eliminate new \ClassName(
        ) {
            return SymbolKind::FUNCTION;
        }

        return SymbolKind::CLASSLIKE; // constant may fall here, this is eliminated later
    }

    private function canBeSymbolName(
        int $pointerBeforeName,
        int $pointerAfterName
    ): bool
    {
        $tokenBeforeName = $this->getTokenBefore($pointerBeforeName);
        $tokenAfterName = $this->getTokenAfter($pointerAfterName);

        if (
            $tokenBeforeName[0] === T_DOUBLE_COLON
            || $tokenBeforeName[0] === T_INSTEADOF
            || $tokenBeforeName[0] === T_AS
            || $tokenBeforeName[0] === T_FUNCTION
            || $tokenBeforeName[0] === T_OBJECT_OPERATOR
            || $tokenBeforeName[0] === T_NAMESPACE
            || $tokenBeforeName[0] === T_CLASS
            || $tokenBeforeName[0] === T_INTERFACE
            || $tokenBeforeName[0] === T_TRAIT
            || $tokenBeforeName[0] === (PHP_VERSION_ID >= 80100 ? T_ENUM : -1)
            || $tokenBeforeName[0] === (PHP_VERSION_ID > 80000 ? T_NULLSAFE_OBJECT_OPERATOR : -1)
            || $tokenAfterName[0] === T_INSTEADOF
            || $tokenAfterName[0] === T_AS
            || $tokenAfterName === ':'
            || $tokenAfterName === '='
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return array{int, string}|string
     */
    private function getTokenBefore(int $pointer)
    {
        do {
            $token = $this->tokens[$pointer];

            if (!is_array($token)) {
                break;
            }

            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $pointer--;
                continue;
            }

            break;
        } while ($pointer >= 0);

        return $token;
    }

    /**
     * @return array{int, string}|string
     */
    private function getTokenAfter(int $pointer)
    {
        do {
            $token = $this->tokens[$pointer];

            if (!is_array($token)) {
                break;
            }

            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $pointer++;
                continue;
            }

            break;
        } while ($pointer < $this->numTokens);

        return $token;
    }

}
