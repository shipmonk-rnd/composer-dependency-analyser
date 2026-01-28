<?php

namespace App;

use Carbon\Carbon;

class ConstHolder
{
    // T_TRAIT after T_CONST - should not corrupt $inClassLevel
    // (CLASS cannot be used as constant name - it's reserved for ::class)
    public const TRAIT = 'some-trait';

    public function test(): void
    {
        // Carbon should be detected here (after const TRAIT definition)
        Carbon::now();
    }
}

class Foo
{
    public function method(): void
    {
        // T_CLASS after T_DOUBLE_COLON - should not corrupt $inClassLevel
        $x = Other::CLASS;

        $fn = function () {
            // closure that could cause spurious $inClassLevel reset
        };
    }

    use App\SomeTrait; // trait use, NOT an import - should be ignored

    public function bar(): void
    {
        // Carbon should be detected here (after ::CLASS and closure)
        Carbon::now();
    }
}

// SomeTrait should NOT be in use statements (trait use is not import)
new SomeTrait();
