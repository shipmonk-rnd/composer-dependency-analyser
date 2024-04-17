<?php

#[\SomeAttribute()]
class ClassWithAttribute {}

#[
    \Assert\NotNull(),
    \Assert\NotBlank(),
]
class ClassWithMultipleAttributes {}
