<?php declare(strict_types = 1);

namespace ShipMonk\Composer;

use PHPUnit\Framework\TestCase;
use function file_get_contents;

class UsedSymbolExtractorTest extends TestCase
{

    public function test(): void
    {
        $code = file_get_contents(__DIR__ . '/data/use-statements.php');
        self::assertNotFalse($code);

        $extractor = new UsedSymbolExtractor($code);
        self::assertSame([
            'GlobalClassname',
            'Regular\Classname',
            'Aliased\Classname',
            'Aliased\Classname2',
            'Fully\Qualified\ClassName',
        ], $extractor->parseUsedSymbols());
    }

}
