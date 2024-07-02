<?php


namespace Relative;

use DateTimeImmutable;

class Foo {}
new namespace\Foo();
new DateTimeImmutable();
new Foo\Bar; // is Relative\Foo\Bar
