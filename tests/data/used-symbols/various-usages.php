<?php


namespace App1;

use DateTimeInterface;
use LogicException as LogicExceptionAlias;
use PHPUnit\Framework\Error;

class Foo {

    public function foo(\DateTimeImmutable $foo): DateTimeInterface|\DateTime
    {
        $class = Error::class;
        return new LogicExceptionAlias();
    }

}



namespace App2;

class Error {

}

trait SomeTrait {

}

class Foo {

    use SomeTrait;

    public function foo(Error $error): void // not PHPUnit\Framework\Error anymore
    {

    }

}



