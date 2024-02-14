<?php

namespace App;

use bool;       // Fatal error: Cannot use bool as bool because 'bool' is a special class name
use int;        // Fatal error: Cannot use int as int because 'int' is a special class name
use float;      // Fatal error: Cannot use float as float because 'float' is a special class name
use string;     // Fatal error: Cannot use string as string because 'string' is a special class name
use null;       // Fatal error: Cannot use null as null because 'null' is a special class name
use array;      // Parse error: syntax error, unexpected token "array"
use object;     // Fatal error: Cannot use object as object because 'object' is a special class name
use resource;   // ! interpreted as classname
use never;      // Fatal error: Cannot use never as never because 'never' is a special class name
use void;       // Fatal error: Cannot use void as void because 'void' is a special class name
use false;      // Fatal error: Cannot use false as false because 'false' is a special class name
use true;       // Fatal error: Cannot use true as true because 'true' is a special class name
use callable;   // Parse error: syntax error, unexpected token "callable"
use self;       // Fatal error: Cannot use self as self because 'self' is a special class name
use parent;     // Fatal error: Cannot use parent as parent because 'parent' is a special class name
use static;     // Parse error: syntax error, unexpected token "static"
use mixed;      // Fatal error: Cannot use mixed as mixed because 'mixed' is a special class name
use iterable;   // Fatal error: Cannot use iterable as iterable because 'iterable' is a special class name

class Native
{

    public function relative1(): bool {}
    public function relative2(): int {}
    public function relative3(): float {}
    public function relative4(): string {}
    public function relative5(): null|bool {}
    public function relative6(): array {}
    public function relative7(): object {}
    public function relative8(): resource {} // Warning: "resource" is not a supported builtin type and will be interpreted as a class name (when no use statement is used)
    public function relative9(): never {}
    public function relative10(): void {}
    public function relative11(): false|null {}
    public function relative12(): true|null {}
    public function relative13(): callable {}
    public function relative14(): self {}
    public function relative15(): parent {}
    public function relative16(): static {}
    public function relative17(): mixed {}
    public function relative18(): iterable {}

    public function fqn1(): \bool {}     // Fatal error: Type declaration 'bool' must be unqualified
    public function fqn2(): \int {}      // Fatal error: Type declaration 'int' must be unqualified
    public function fqn3(): \float {}    // Fatal error: Type declaration 'float' must be unqualified
    public function fqn4(): \string {}   // Fatal error: Type declaration 'string' must be unqualified
    public function fqn5(): \null {}     // Fatal error: Type declaration 'null' must be unqualified
    public function fqn6(): \array {}
    public function fqn7(): \object {}   // Fatal error: Type declaration 'object' must be unqualified
    public function fqn8(): \resource {} // interpreted as classname
    public function fqn9(): \never {}    // Fatal error: Type declaration 'never' must be unqualified
    public function fqn10(): \void {}    // Fatal error: Type declaration 'void' must be unqualified
    public function fqn11(): \false {}   // Fatal error: Type declaration 'false' must be unqualified
    public function fqn12(): \true {}    // Fatal error: Type declaration 'true' must be unqualified
    public function fqn13(): \callable {}
    public function fqn14(): \self {}    // Fatal error: '\self' is an invalid class name
    public function fqn15(): \parent {}  // Fatal error: '\parent' is an invalid class name
    public function fqn16(): \static {}  // Fatal error: '\static' is an invalid class name
    public function fqn17(): \mixed {}   // Fatal error: Type declaration 'mixed' must be unqualified
    public function fqn18(): \iterable {}// Fatal error: Type declaration 'iterable' must be unqualified

}
