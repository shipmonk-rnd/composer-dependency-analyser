<?php

namespace App;

use const PHP_EOL;
use function strpos;
use function PHPUnit\Framework\assertArrayHasKey as assertAlias;

echo PHP_EOL . "\n";
echo \DIRECTORY_SEPARATOR . "\n";

\strlen('');
strpos('', '');
assertAlias('', []);
