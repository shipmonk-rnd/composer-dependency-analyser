<?php

new \DateTimeImmutable();
new DateTime;
new PHPUnit\Framework\Error();

PHPUnit\Framework\assertSame(1, 1);

class Foo {
    public function someFunction(string $foo): void
    {
        user_defined_function();
    }
}

// Test for issue #224: unqualified static access in global scope
$class = UnknownClass::class;
UnknownClass::staticMethod();
UnknownClass::CONSTANT;

// These should NOT be detected as class usages
self::FOO;
static::bar();
parent::__construct();
