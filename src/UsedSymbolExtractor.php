<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use function count;
use function ltrim;
use function token_get_all;
use const PHP_VERSION_ID;
use const T_AS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
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
     * @return list<string>
     */
    public function parseUsedSymbols(): array
    {
        $statements = [];

        while ($token = $this->getNextEffectiveToken()) {
            if ($token[0] === T_USE && $this->level === 0) {
                $usedClass = $this->parseSimpleUseStatement();

                if ($usedClass !== null) {
                    $statements[] = $usedClass;
                }
            }

            if (PHP_VERSION_ID >= 80000 && $token[0] === T_NAME_FULLY_QUALIFIED) {
                $statements[] = $this->normalizeBackslash($token[1]);
            }
        }

        return $statements;
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

            if ($token === '{') {
                $this->level++;
            } elseif ($token === '}') {
                $this->level--;
            }

            return $token;
        }

        return null;
    }

    /**
     * Parses simple use statement like:
     *
     * use Foo\Bar;
     * use Foo\Bar as Alias;
     *
     * Does not support bracket syntax nor comma-separated statements:
     *
     * use Foo\{ Bar, Baz };
     * use Foo\Bar, Foo\Baz;
     */
    private function parseSimpleUseStatement(): ?string
    {
        $class = '';

        while ($token = $this->getNextEffectiveToken()) {
            if ($token[0] === T_STRING) {
                $class .= $token[1];
            } elseif (
                PHP_VERSION_ID >= 80000 &&
                ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED)
            ) {
                $class .= $token[1];
            } elseif ($token[0] === T_NS_SEPARATOR) {
                $class .= '\\';
            } elseif ($token[0] === T_AS || $token === ';') {
                return $this->normalizeBackslash($class);
            } else {
                break;
            }
        }

        return null;
    }

    private function normalizeBackslash(string $class): string
    {
        return ltrim($class, '\\');
    }

}
