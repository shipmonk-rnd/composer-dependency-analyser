<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use PhpToken;
use function array_values;
use function count;
use function explode;
use function ltrim;
use function ord;
use function strlen;
use function strtolower;
use function substr;
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
use const T_OPEN_TAG;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

class UsedSymbolExtractor
{

    /**
     * @var list<PhpToken>
     */
    private array $tokens;

    private int $numTokens;

    private int $pointer = 0;

    public function __construct(string $code)
    {
        $this->tokens = array_values(PhpToken::tokenize($code));
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
        array $knownSymbols,
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

            switch ($token->id) {
                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                case T_ENUM:
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

                case T_ATTRIBUTE:
                    $inAttributeSquareLevel = ++$squareLevel;
                    break;

                case T_NAMESPACE:
                    // namespace change
                    $inGlobalScope = false;
                    $useStatements = [];
                    $useStatementKinds = [];
                    break;

                case T_NAME_FULLY_QUALIFIED:
                    $symbolName = $this->normalizeBackslash($token->text);
                    $lowerSymbolName = strtolower($symbolName);
                    $kind = $knownSymbols[$lowerSymbolName] ?? $this->getFqnSymbolKind($this->pointer - 2, $this->pointer, $inAttributeSquareLevel !== null);
                    $usedSymbols[$kind][$symbolName][] = $token->line;
                    break;

                case T_NAME_QUALIFIED:
                    [$neededAlias] = explode('\\', $token->text, 2);

                    if (isset($useStatements[$neededAlias])) {
                        $symbolName = $useStatements[$neededAlias] . substr($token->text, strlen($neededAlias));
                    } elseif ($inGlobalScope) {
                        $symbolName = $token->text;
                    } else {
                        break;
                    }

                    $lowerSymbolName = strtolower($symbolName);
                    $kind = $knownSymbols[$lowerSymbolName] ?? $this->getFqnSymbolKind($this->pointer - 2, $this->pointer, $inAttributeSquareLevel !== null);
                    $usedSymbols[$kind][$symbolName][] = $token->line;

                    break;

                case T_STRING:
                    $name = $token->text;
                    $lowerName = strtolower($name);
                    $pointerBeforeName = $this->pointer - 2;
                    $pointerAfterName = $this->pointer;

                    if (!$this->canBeSymbolName($pointerBeforeName, $pointerAfterName)) {
                        break;
                    }

                    if (isset($useStatements[$name])) {
                        $symbolName = $useStatements[$name];
                        $kind = $useStatementKinds[$name];
                        $usedSymbols[$kind][$symbolName][] = $token->line;

                    } elseif (isset($knownSymbols[$lowerName])) {
                        $symbolName = $name;
                        $kind = $knownSymbols[$lowerName];

                        if (!$inGlobalScope && $kind === SymbolKind::CLASSLIKE) {
                            break; // cannot use class-like symbols in non-global scope when not imported
                        }

                        $usedSymbols[$kind][$symbolName][] = $token->line;

                    } elseif (
                        $inGlobalScope
                        && $this->getTokenAfter($pointerAfterName)->id === T_DOUBLE_COLON
                    ) {
                        // unqualified static access (e.g., Foo::class, Foo::method(), Foo::CONSTANT) in global scope
                        // register to allow detection of classes not in $knownSymbols
                        $usedSymbols[SymbolKind::CLASSLIKE][$name][] = $token->line;
                    }

                    break;

                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                    $level++;
                    break;

                case ord('{'):
                    $level++;
                    break;

                case ord('}'):
                    if ($level === $inClassLevel) {
                        $inClassLevel = null;
                    }

                    $level--;
                    break;

                case ord('['):
                    $squareLevel++;
                    break;

                case ord(']'):
                    if ($squareLevel === $inAttributeSquareLevel) {
                        $inAttributeSquareLevel = null;
                    }

                    $squareLevel--;
                    break;
            }
        }

        return $usedSymbols;
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

            switch ($token->id) {
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                case T_OPEN_TAG:
                    continue 2;

                case T_STRING:
                    $alias = $token->text;

                    if (!$explicitAlias) {
                        $class .= $alias;
                    }

                    break;

                case T_NAME_QUALIFIED:
                case T_NAME_FULLY_QUALIFIED:
                    $class .= $token->text;
                    $classSplit = explode('\\', $token->text);
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

                case ord(','):
                    $statements[$alias] = [$groupRoot . $class, $kind];

                    if (!$kindFrozen) {
                        $kind = SymbolKind::CLASSLIKE;
                    }

                    $class = '';
                    $alias = '';
                    $explicitAlias = false;
                    break;

                case ord(';'):
                    $statements[$alias] = [$groupRoot . $class, $kind];
                    return $statements;

                case ord('{'):
                    $kindFrozen = ($kind === SymbolKind::FUNCTION || $kind === SymbolKind::CONSTANT);
                    $groupRoot = $class;
                    $class = '';
                    break;

                case ord('}'):
                    break;

                default:
                    return $statements;
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
        bool $inAttribute,
    ): int
    {
        if ($inAttribute) {
            return SymbolKind::CLASSLIKE;
        }

        $tokenBeforeName = $this->getTokenBefore($pointerBeforeName);
        $tokenAfterName = $this->getTokenAfter($pointerAfterName);

        if (
            $tokenAfterName->text === '('
            && $tokenBeforeName->id !== T_NEW // eliminate new \ClassName(
        ) {
            return SymbolKind::FUNCTION;
        }

        return SymbolKind::CLASSLIKE; // constant may fall here, this is eliminated later
    }

    private function canBeSymbolName(
        int $pointerBeforeName,
        int $pointerAfterName,
    ): bool
    {
        $tokenBeforeName = $this->getTokenBefore($pointerBeforeName);
        $tokenAfterName = $this->getTokenAfter($pointerAfterName);

        if (
            $tokenBeforeName->id === T_DOUBLE_COLON
            || $tokenBeforeName->id === T_INSTEADOF
            || $tokenBeforeName->id === T_AS
            || $tokenBeforeName->id === T_FUNCTION
            || $tokenBeforeName->id === T_OBJECT_OPERATOR
            || $tokenBeforeName->id === T_NAMESPACE
            || $tokenBeforeName->id === T_CLASS
            || $tokenBeforeName->id === T_INTERFACE
            || $tokenBeforeName->id === T_TRAIT
            || $tokenBeforeName->id === T_ENUM
            || $tokenBeforeName->id === T_NULLSAFE_OBJECT_OPERATOR
            || $tokenAfterName->id === T_INSTEADOF
            || $tokenAfterName->id === T_AS
            || $tokenAfterName->text === ':'
            || $tokenAfterName->text === '='
        ) {
            return false;
        }

        return true;
    }

    private function getTokenBefore(int $pointer): PhpToken
    {
        while ($pointer >= 0) {
            $token = $this->tokens[$pointer];

            if ($token->isIgnorable()) {
                $pointer--;
                continue;
            }

            return $token;
        }

        return $this->tokens[0];
    }

    private function getTokenAfter(int $pointer): PhpToken
    {
        while ($pointer < $this->numTokens) {
            $token = $this->tokens[$pointer];

            if ($token->isIgnorable()) {
                $pointer++;
                continue;
            }

            return $token;
        }

        return $this->tokens[$this->numTokens - 1];
    }

}
