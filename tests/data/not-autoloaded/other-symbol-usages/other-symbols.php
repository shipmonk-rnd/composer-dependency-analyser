<?php

use function PHPUnit\Framework\assertSame;

echo \DIRECTORY_SEPARATOR;
echo \strlen('');
echo substr(' ', 0, 1);

\PHPUnit\Framework\assertArrayHasKey('', []);
assertSame('', '');
PHP_EOL;
