<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use function file_get_contents;
use const PHP_VERSION_ID;

class UsedSymbolExtractorTest extends TestCase
{

    public function test(): void
    {
        $code = file_get_contents(__DIR__ . '/data/use-statements.php');
        self::assertNotFalse($code);

        $extractor = new UsedSymbolExtractor($code);
        $expected = [
            'GlobalClassname',
            'Regular\Classname',
            'Aliased\Classname',
            'Aliased\Classname2',
        ];

        if (PHP_VERSION_ID >= 80000) {
            $expected[] = 'Fully\Qualified\ClassName';
        }

        self::assertSame($expected, $extractor->parseUsedSymbols());
    }

}
