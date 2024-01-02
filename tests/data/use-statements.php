<?php

namespace Foo;

class Foo {
    use NotAUseStatement; // no false positive
}

use \GlobalClassname;
use Regular\Classname;
use Aliased\Classname as Alias;
use Aliased\Classname2 as Alias2,
    Aliased\ClassnameAfterDash; // not supported

use function Braced\{function1, function2};
use const Braced\{CONST1, CONST2};

use Braced\Combined\ { // not supported
    Class1,
    Subnamespace\Class2,
    AliasedClass as BracedAliasedClassAlias,
    function function1,
    function Subnamespace\function2,
    const CONST1,
    const Subnamespace\CONST2
};

class Bar {
    use StillNotAUseStatement; // no false positive
}


use function Regular\function1;
use function Aliased\function2 as function2Alias;

use const Regular\CONST1;

new class ($foo) {
    use NotAUseStatement; // no false positive
};

function () use ($foo) {};

new \Fully\Qualified\ClassName();
new Not\Fully\Qualified\ClassName();

namespace Bar {
    use WithinBracketNamespace; // not supported
}


\DIRECTORY_SEPARATOR;
