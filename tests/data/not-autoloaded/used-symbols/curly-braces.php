<?php

namespace Curly;

trait MyTrait {}

class Foo {

    public function foo($foo): void
    {
        echo "${$foo}";
    }

    use MyTrait; // not a use statement, $level should not be 0 here

}

echo MyTrait::class;
