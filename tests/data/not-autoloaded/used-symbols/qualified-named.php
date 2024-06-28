<?php

// Qualified name without use statement
echo Foo\Bar::class;
echo Foo2\Baz::MY_CONST;
echo Foo2\Baz::my_function();

echo Foo\Bar//com
::class;
echo Foo2\Baz//com
::MY_CONST;
echo Foo2\Baz//com
::my_function();

echo Foo\Bar/*com*/::class;
echo Foo2\Baz/*com*/::MY_CONST;
echo Foo2\Baz/*com*/::my_function();

echo Foo\Bar/**doccom*/::class;
echo Foo2\Baz/**doccom*/::MY_CONST;
echo Foo2\Baz/**doccom*/::my_function();

// Fully qualified
echo \Foo2\Bar::class;
