<?php

namespace BrokenParent\Package;

// Extends a class that is not available at runtime. The class itself is autoloadable
// (registered in composer's classmap), but loading it via reflection throws a plain Error,
// not a ReflectionException. See https://github.com/shipmonk-rnd/composer-dependency-analyser/issues/271
class Clazz extends NonExistentParent {}
