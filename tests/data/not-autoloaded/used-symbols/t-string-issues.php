<?php

namespace PDO;

use function _; // e.g. https://www.php.net/manual/en/function.-.php
use function array_filter;
use function array_values;
use function array_map;
use function array_walk;

class Test
{
    public const string SESSION_ID = '...';

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

// https://github.com/shipmonk-rnd/composer-dependency-analyser/issues/216
// Class/interface/trait/enum definition names must not be detected as function usages
class Value {}
interface Collect {}
trait Tap {}
