<?php

namespace PDO;

use function _; // e.g. https://www.php.net/manual/en/function.-.php
use function array_filter;
use function array_values;
use function array_map;
use function array_walk;

class Test
{
    use SomeTrait {
        array_filter insteadof array_values;
        array_map as array_walk;
    }

    public function _(): void {
        $this->array_filter();
        $this?->array_filter();
        self::array_filter();
    }

}
