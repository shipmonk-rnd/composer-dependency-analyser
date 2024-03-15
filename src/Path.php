<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

use ShipMonk\ComposerDependencyAnalyser\Exception\InvalidPathException;
use function array_pop;
use function end;
use function implode;
use function is_dir;
use function is_file;
use function preg_match;
use function preg_split;
use function realpath;
use const DIRECTORY_SEPARATOR;

/**
 * Inspired by Nette\Utils\FileSystem
 *
 * @license https://github.com/nette/utils/blob/v4.0.4/license.md
 */
class Path
{

    /**
     * @throws InvalidPathException
     */
    public static function realpath(string $path): string
    {
        if (!is_file($path) && !is_dir($path)) {
            throw new InvalidPathException("'$path' is not a file nor directory");
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new InvalidPathException("Unable to realpath '$path'");
        }

        return $realPath;
    }

    public static function resolve(string $basePath, string $path): string
    {
        return self::isAbsolute($path)
            ? $path
            : self::normalize($basePath . '/' . $path);
    }

    public static function isAbsolute(string $vendorDir): bool
    {
        return (bool) preg_match('#([a-z]:)?[/\\\\]|[a-z][a-z0-9+.-]*://#Ai', $vendorDir);
    }

    public static function normalize(string $path): string
    {
        /** @var list<string> $parts */
        $parts = $path === ''
            ? []
            : preg_split('~[/\\\\]+~', $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '..' && $result !== [] && end($result) !== '..' && end($result) !== '') {
                array_pop($result);
            } elseif ($part !== '.') {
                $result[] = $part;
            }
        }

        return $result === ['']
            ? DIRECTORY_SEPARATOR
            : implode(DIRECTORY_SEPARATOR, $result);
    }

}
