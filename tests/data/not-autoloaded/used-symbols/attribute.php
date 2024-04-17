<?php

#[\SomeAttribute()]
class ClassWithAttribute {}

#[
    \Assert\NotNull(foo: []),
    \Assert\NotBlank(),
]
class ClassWithMultipleAttributes {}
